<?php

namespace App\Services;

use App\Jobs\GenerarAlertaQuiebreJob;
use App\Models\MovimientoProducto;
use App\Models\Notificacion;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockAlertService
{
    /**
     * Determina la zona de inventario del producto:
     * - 'QUIEBRE': stock actual <= 0
     * - 'ALERTA':  stock actual > 0 y stock actual <= (stock_minimo * factor_alerta)
     * - null:      stock saludable (no requiere notificación)
     *
     * @param Producto $producto
     * @return string|null
     */
    public function determinarZona(Producto $producto): ?string
    {
        $stockActual = (float) ($producto->ProductoStockActual ?? 0);
        $stockMinimo = (float) ($producto->ProductoStockMinimo ?? 0);
        $factorAlerta = (float) Config::get('stock.factor_alerta', 1.3);

        if ($stockActual <= 0) {
            return 'QUIEBRE';
        }

        $umbralAlerta = $stockMinimo * $factorAlerta;
        if ($stockActual <= $umbralAlerta) {
            return 'ALERTA';
        }

        return null;
    }

    /**
     * Obtiene los usuarios activos con rol Jefe de Tienda o Logístico.
     *
     * @return Collection<int, Usuario>
     */
    public function obtenerDestinatarios(): Collection
    {
        return Usuario::whereHas('rolUser', function ($q) {
            $q->whereIn('Rol_UserEmpleadocol', ['JEFE_TIENDA', 'LOGISTICO']);
        })->where('UsuarioEliminado', 'N')->get();
    }

    /**
     * Verifica si TODOS los destinatarios ya recibieron una notificación para este producto
     * en las últimas 24 horas (anti-spam por usuario y por producto).
     * Si algún destinatario aún no ha recibido la notificación, retorna false para procesarlo.
     *
     * @param string $productoId
     * @param Collection $destinatarios
     * @return bool
     */
    public function existeAlertaReciente(string $productoId, Collection $destinatarios): bool
    {
        $destinatariosIds = $destinatarios->pluck('UsuarioId');
        if ($destinatariosIds->isEmpty()) {
            return false;
        }

        $cooldownHoras = (int) Config::get('stock.cooldown_horas', 24);

        $yaNotificados = Notificacion::where('NotificacionCategoria', 'STOCK_CRITICO')
            ->where('NotificacionReferenciaId', $productoId)
            ->where('NotificacionFecha', '>=', now()->subHours($cooldownHoras))
            ->whereIn('NotificacionUsuarioId', $destinatariosIds)
            ->distinct()
            ->pluck('NotificacionUsuarioId');

        return $yaNotificados->count() >= $destinatariosIds->count();
    }

    /**
     * Calcula la cantidad económica de reposición sugerida (EOQ Wilson)
     * con fallback determinista si no existe historial de demanda suficiente.
     *
     * @param Producto $producto
     * @return float
     */
    public function calcularCantidadSugerida(Producto $producto): float
    {
        $diasDemanda = (int) Config::get('stock.dias_analisis_demanda', 90);
        $factorAnual = (int) Config::get('stock.factor_anualizacion', 4);
        $costoOrden = (float) Config::get('stock.costo_orden', 50.0);
        $tasaAlmacenamiento = (float) Config::get('stock.tasa_almacenamiento', 0.10);

        // Demanda anual D: sumatoria de salidas (tipo 'S') en los últimos 90 días * factor anual (4)
        $salidasHistoricas = (float) MovimientoProducto::where('Movimiento_producto_ProductoId', $producto->ProductoId)
            ->where('Movimiento_productoTipoMovimiento', 'S')
            ->where('Movimiento_productoEliminado', 'N')
            ->where('Movimiento_productoFecha_Movimiento', '>=', now()->subDays($diasDemanda))
            ->sum('Movimiento_productoCantidadSalida');

        $demandaAnual = $salidasHistoricas * $factorAnual;

        // Obtener costo unitario de compra de la unidad base para estimar H (costo anual de almacenamiento)
        $detalleBase = $producto->todasDetalleProductoMedidas
            ? $producto->todasDetalleProductoMedidas->firstWhere('Detalle_Producto_medida_factor_conversion', 1)
            : null;

        if (!$detalleBase && $producto->todasDetalleProductoMedidas) {
            $detalleBase = $producto->todasDetalleProductoMedidas->first();
        }

        $precioCompra = (float) ($detalleBase?->Detalle_Producto_medida_precio_compra ?? 0);
        $h = $precioCompra > 0 ? ($precioCompra * $tasaAlmacenamiento) : 1.0;

        $stockMin = (float) ($producto->ProductoStockMinimo ?? 0);
        $stockMax = (float) ($producto->ProductoStockMaximo ?? 0);
        $stockAct = (float) ($producto->ProductoStockActual ?? 0);

        if ($demandaAnual > 0 && $h > 0) {
            // Fórmula Wilson EOQ: sqrt((2 * D * S) / H)
            $q = sqrt((2 * $demandaAnual * $costoOrden) / $h);
            $cantidad = ceil($q);
        } else {
            // Fallback sin historial: max(stock_minimo, stock_maximo - stock_actual)
            $diferencia = $stockMax > $stockAct ? ($stockMax - $stockAct) : $stockMin;
            $cantidad = max($stockMin, $diferencia);
        }

        // Respetar umbrales mínimos y máximos configurados
        if ($stockMax > 0 && $cantidad > $stockMax) {
            $cantidad = $stockMax;
        }
        if ($stockMin > 0 && $cantidad < $stockMin) {
            $cantidad = $stockMin;
        }

        return max(1.0, (float) $cantidad);
    }

    /**
     * Evalúa el estado del producto tras una actualización de inventario.
     * Si entra en zona de alerta o quiebre, y no se encuentra en enfriamiento,
     * despacha el trabajo asíncrono para generar la alerta con IA.
     *
     * @param Producto $producto
     * @return string|null Zona detectada ('QUIEBRE', 'ALERTA' o null)
     */
    public function evaluar(Producto $producto): ?string
    {
        $zona = $this->determinarZona($producto);
        if ($zona === null) {
            return null;
        }

        $destinatarios = $this->obtenerDestinatarios();
        if ($destinatarios->isEmpty()) {
            Log::info("StockAlertService: No hay usuarios activos en roles JEFE_TIENDA o LOGISTICO.");
            return null;
        }

        if ($this->existeAlertaReciente($producto->ProductoId, $destinatarios)) {
            Log::info("StockAlertService: Cooldown anti-spam activo (24h) para producto {$producto->ProductoId}.");
            return null;
        }

        $cantidadSugerida = $this->calcularCantidadSugerida($producto);

        // Despachar Job asíncrono en segundo plano tras el commit de la transacción
        GenerarAlertaQuiebreJob::dispatch(
            $producto->ProductoId,
            $zona,
            $cantidadSugerida
        )->afterCommit();

        return $zona;
    }
}
