<?php

namespace App\Services;

use App\Models\AuditoriaRoturaStock;
use App\Models\DetallePedidoProductos;
use App\Models\DetalleRoturaStock;
use App\Models\Producto;
use App\Support\AuditHelper;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RoturaStockService
{
    /**
     * Registrar un INTENTO de rotura de stock.
     * Ocurre cuando un vendedor intenta pedir más de lo que hay físicamente en stock.
     */
    public function registrarIntento(array $datos): DetalleRoturaStock
    {
        $productoId = $datos['producto_id'] ?? $datos['ProductoId'] ?? null;
        $cantidadSolicitada = (float) ($datos['cantidad_solicitada'] ?? 0);
        $cantidadDisponible = (float) ($datos['cantidad_disponible'] ?? $datos['cantidad_disponible_momento'] ?? 0);
        $cantidadFaltante = max(0.0, (float) ($datos['cantidad_faltante'] ?? ($cantidadSolicitada - $cantidadDisponible)));
        $pedidoId = $datos['pedido_id'] ?? null;
        $detallePedidoId = $datos['detalle_pedido_id'] ?? null;
        $usuario = $datos['usuario'] ?? $datos['usuario_id'] ?? AuditHelper::getCurrentUser();
        $observaciones = $datos['observaciones'] ?? 'Intento de venta sin stock suficiente';

        $producto = Producto::find($productoId);

        // 1. Guardar en la tabla especializada de auditoría granular detalle_rotura_stock
        $rotura = DetalleRoturaStock::create([
            'pedido_id' => $pedidoId,
            'detalle_pedido_id' => $detallePedidoId,
            'producto_id' => $productoId,
            'orden_compra_codigo' => $datos['orden_compra_codigo'] ?? null,
            'cantidad_solicitada' => $cantidadSolicitada,
            'cantidad_disponible_momento' => $cantidadDisponible,
            'cantidad_faltante' => $cantidadFaltante,
            'tipo_rotura' => 'INTENTO',
            'fecha_hora' => now(),
            'usuario_id' => $usuario,
            'observaciones' => $observaciones,
        ]);

        // 2. Si hay detalle de pedido vinculado, marcar banderas
        if ($detallePedidoId) {
            DetallePedidoProductos::where('Detalle_Pedido_ProductosId', $detallePedidoId)->update([
                'Detalle_Pedido_ProductosTuvoRotura' => 'S',
                'Detalle_Pedido_ProductosCantidadFaltanteRotura' => $cantidadFaltante,
            ]);
        }

        // 3. Compatibilidad con tabla auditoria_roturas_stock para telemetría
        try {
            AuditoriaRoturaStock::create([
                'pedido_id' => $pedidoId,
                'cantidad_solicitada' => $cantidadSolicitada,
                'cantidad_disponible_fisica' => $cantidadDisponible,
                'deficit_unidades' => $cantidadFaltante,
                'tipo_rotura' => 'BACKORDER',
                'rompe_stock_seguridad' => true,
                'categoria_id' => $producto?->Producto_Categoria_ProductoId,
                'usuario' => $usuario,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {}

        return $rotura;
    }

    /**
     * Confirmar una rotura de stock definitiva.
     * Ocurre cuando el pedido se cancela o se despacha incompleto por falta de existencias físicas.
     */
    public function confirmarRotura(array $datos): DetalleRoturaStock
    {
        $productoId = $datos['producto_id'] ?? $datos['ProductoId'] ?? null;
        $cantidadSolicitada = (float) ($datos['cantidad_solicitada'] ?? 0);
        $cantidadDisponible = (float) ($datos['cantidad_disponible'] ?? $datos['cantidad_disponible_momento'] ?? 0);
        $cantidadFaltante = (float) ($datos['cantidad_faltante'] ?? max(0.0, $cantidadSolicitada - $cantidadDisponible));
        $pedidoId = $datos['pedido_id'] ?? null;
        $detallePedidoId = $datos['detalle_pedido_id'] ?? null;
        $usuario = $datos['usuario'] ?? $datos['usuario_id'] ?? AuditHelper::getCurrentUser();
        $observaciones = $datos['observaciones'] ?? 'Quiebre de stock confirmado en venta';

        $producto = Producto::find($productoId);

        $rotura = DetalleRoturaStock::create([
            'pedido_id' => $pedidoId,
            'detalle_pedido_id' => $detallePedidoId,
            'producto_id' => $productoId,
            'orden_compra_codigo' => $datos['orden_compra_codigo'] ?? null,
            'cantidad_solicitada' => $cantidadSolicitada,
            'cantidad_disponible_momento' => $cantidadDisponible,
            'cantidad_faltante' => $cantidadFaltante,
            'tipo_rotura' => 'CONFIRMADA',
            'fecha_hora' => now(),
            'usuario_id' => $usuario,
            'observaciones' => $observaciones,
        ]);

        if ($detallePedidoId) {
            DetallePedidoProductos::where('Detalle_Pedido_ProductosId', $detallePedidoId)->update([
                'Detalle_Pedido_ProductosTuvoRotura' => 'S',
                'Detalle_Pedido_ProductosCantidadFaltanteRotura' => $cantidadFaltante,
            ]);
        }

        try {
            AuditoriaRoturaStock::create([
                'pedido_id' => $pedidoId,
                'cantidad_solicitada' => $cantidadSolicitada,
                'cantidad_disponible_fisica' => $cantidadDisponible,
                'deficit_unidades' => $cantidadFaltante,
                'tipo_rotura' => 'VENTA_PERDIDA',
                'rompe_stock_seguridad' => true,
                'categoria_id' => $producto?->Producto_Categoria_ProductoId,
                'usuario' => $usuario,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {}

        return $rotura;
    }

    /**
     * Listar roturas registradas con filtros.
     */
    public function listarRoturas(array $filtros = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = DetalleRoturaStock::with(['producto.categoria', 'pedido']);

        if (!empty($filtros['tipo_rotura'])) {
            $query->porTipo($filtros['tipo_rotura']);
        }

        if (!empty($filtros['producto_id'])) {
            $query->where('producto_id', $filtros['producto_id']);
        }

        if (!empty($filtros['pedido_id'])) {
            $query->where('pedido_id', $filtros['pedido_id']);
        }

        if (!empty($filtros['fecha_desde'])) {
            $query->whereDate('fecha_hora', '>=', $filtros['fecha_desde']);
        }

        if (!empty($filtros['fecha_hasta'])) {
            $query->whereDate('fecha_hora', '<=', $filtros['fecha_hasta']);
        }

        $query->orderBy('fecha_hora', 'desc');

        return $perPage > 0 ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Resumen agregado de roturas para telemetría y dashboard.
     */
    public function obtenerResumenDashboard(?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $fechaDesde = $fechaDesde ?: now()->startOfMonth()->toDateString();
        $fechaHasta = $fechaHasta ?: now()->toDateString();

        $queryBase = DetalleRoturaStock::whereBetween('fecha_hora', [$fechaDesde, $fechaHasta . ' 23:59:59']);

        $totalIntentos = (clone $queryBase)->where('tipo_rotura', 'INTENTO')->count();
        $totalConfirmadas = (clone $queryBase)->where('tipo_rotura', 'CONFIRMADA')->count();
        $unidadesFaltantes = (float) (clone $queryBase)->where('tipo_rotura', 'CONFIRMADA')->sum('cantidad_faltante');

        $topProductos = (clone $queryBase)
            ->join('Producto as p', 'detalle_rotura_stock.producto_id', '=', 'p.ProductoId')
            ->select(
                'detalle_rotura_stock.producto_id',
                'p.ProductoNombre as producto',
                DB::raw("COUNT(CASE WHEN detalle_rotura_stock.tipo_rotura = 'CONFIRMADA' THEN 1 END) as confirmadas"),
                DB::raw("COUNT(CASE WHEN detalle_rotura_stock.tipo_rotura = 'INTENTO' THEN 1 END) as intentos"),
                DB::raw('SUM(detalle_rotura_stock.cantidad_faltante) as total_faltante')
            )
            ->groupBy('detalle_rotura_stock.producto_id', 'p.ProductoNombre')
            ->orderByDesc('confirmadas')
            ->limit(10)
            ->get();

        return [
            'periodo' => [
                'desde' => $fechaDesde,
                'hasta' => $fechaHasta,
            ],
            'metricas' => [
                'total_intentos' => $totalIntentos,
                'total_confirmadas' => $totalConfirmadas,
                'unidades_faltantes' => round($unidadesFaltantes, 2),
                'ratio_resolucion_pct' => ($totalIntentos + $totalConfirmadas) > 0
                    ? round(($totalIntentos / ($totalIntentos + $totalConfirmadas)) * 100, 1)
                    : 100.0,
            ],
            'top_productos' => $topProductos,
        ];
    }
}
