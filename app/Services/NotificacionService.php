<?php

namespace App\Services;

use App\Models\AjusteInventario;
use App\Models\Notificacion;
use App\Models\Producto;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NotificacionService
{
    /**
     * Generar ID correlativo NOT-00001
     */
    public function getNextId(): string
    {
        $last = DB::table('Notificacion')
            ->select('NotificacionId')
            ->where('NotificacionId', 'like', 'NOT-%')
            ->orderByRaw('LEN(NotificacionId) DESC, NotificacionId DESC')
            ->first();

        if (!$last) {
            return 'NOT-00001';
        }

        $num = (int) preg_replace('/[^0-9]/', '', $last->NotificacionId);
        return 'NOT-' . str_pad((string) ($num + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Crear una notificación y almacenarla en BD y en buffer de tiempo real (SSE)
     */
    public function crear(array $data): Notificacion
    {
        $id = $this->getNextId();
        $silenciosa = !empty($data['silenciosa']) || (($data['NotificacionSilenciosa'] ?? 'N') === 'S');

        // Estructura de diseño para la tarjeta UI (estilo Dynamic Island / Toast oscuro de la imagen adjunta)
        $cardDesign = $data['datos_tarjeta'] ?? [
            'badge' => [
                'texto' => $data['badge_texto'] ?? 'Notificación del Sistema',
                'tipo' => $data['badge_tipo'] ?? 'info', // 'success', 'warning', 'danger', 'info'
                'color' => $data['badge_color'] ?? '#10B981', // Verde esmeralda por defecto
                'icono' => $data['badge_icono'] ?? 'check_circle',
            ],
            'entidad' => 'COMERCIAL VALENCIA',
            'referencia_codigo' => $data['referencia_id'] ?? $id,
            'flujo' => [
                'origen' => $data['flujo_origen'] ?? 'Almacén Central',
                'destino' => $data['flujo_destino'] ?? 'Control de Stock',
                'indicador' => $data['flujo_indicador'] ?? 'Actualizado',
            ],
            'impacto_financiero' => $data['impacto_financiero'] ?? null,
            'accion' => [
                'texto' => $data['accion_texto'] ?? 'Ver Detalles',
                'url' => $data['accion_url'] ?? null,
            ],
            'modo_audio' => $silenciosa ? 'silencioso' : 'audible',
        ];

        $notificacion = Notificacion::create([
            'NotificacionId' => $id,
            'NotificacionTitulo' => $data['titulo'] ?? 'Alerta de Inventario',
            'NotificacionMensaje' => $data['mensaje'] ?? '',
            'NotificacionTipo' => $data['tipo'] ?? 'info',
            'NotificacionCategoria' => $data['categoria'] ?? 'INVENTARIO',
            'NotificacionReferenciaId' => $data['referencia_id'] ?? null,
            'NotificacionDatos' => $cardDesign,
            'NotificacionSilenciosa' => $silenciosa ? 'S' : 'N',
            'NotificacionLeida' => 'N',
            'NotificacionFecha' => now(),
            'NotificacionUsuarioId' => $data['usuario_id'] ?? 'TODOS',
            'NotificacionEliminado' => 'N',
        ]);

        return $notificacion;
    }

    /**
     * Helper específico para notificar registro de Merma / Desperdicio con Impacto Financiero
     */
    public function notificarMermaRegistrada(
        AjusteInventario $ajuste,
        Producto $producto,
        float $stockAnterior,
        float $stockActual,
        array $impactoFinanciero,
        bool $silenciosa = false
    ): Notificacion {
        $costoPerdido = number_format($impactoFinanciero['costo_total_perdido'] ?? 0, 2);
        $cantidad = number_format((float) $ajuste->Ajuste_inventario_cantidad, 2);
        $tipo = $ajuste->Ajuste_inventario_tipo;

        return $this->crear([
            'titulo' => "{$tipo} Registrada: {$producto->ProductoNombre}",
            'mensaje' => "Se descontaron {$cantidad} UND. Pérdida económica estimada: S/ {$costoPerdido}.",
            'tipo' => 'merma',
            'categoria' => 'INVENTARIO',
            'referencia_id' => $ajuste->Ajuste_inventario_id,
            'silenciosa' => $silenciosa,
            'badge_texto' => "{$tipo} Confirmada",
            'badge_tipo' => 'warning',
            'badge_color' => '#F59E0B',
            'badge_icono' => 'alert_circle',
            'flujo_origen' => "Stock Anterior: {$stockAnterior}",
            'flujo_destino' => "Stock Actual: {$stockActual}",
            'flujo_indicador' => "-{$cantidad} UND",
            'impacto_financiero' => [
                'costo_total_perdido' => $impactoFinanciero['costo_total_perdido'] ?? 0,
                'venta_total_perdida' => $impactoFinanciero['venta_total_perdida'] ?? 0,
                'moneda' => 'S/',
                'costo_unitario' => $impactoFinanciero['costo_unitario'] ?? 0,
            ],
            'accion_texto' => 'Ver Detalles de Merma',
            'accion_url' => "/api/ajustes/{$ajuste->Ajuste_inventario_id}",
        ]);
    }

    /**
     * Helper específico para notificar Reversión de Merma (Restauración de Stock)
     */
    public function notificarReversionMerma(
        AjusteInventario $ajuste,
        Producto $producto,
        float $stockAnterior,
        float $stockActual,
        float $cantidadRevertida,
        bool $silenciosa = false
    ): Notificacion {
        $cantStr = number_format($cantidadRevertida, 2);

        return $this->crear([
            'titulo' => "Ajuste Revertido: {$producto->ProductoNombre}",
            'mensaje' => "Se reintegraron {$cantStr} UND al stock tras la anulación del ajuste {$ajuste->Ajuste_inventario_id}.",
            'tipo' => 'reversion',
            'categoria' => 'INVENTARIO',
            'referencia_id' => $ajuste->Ajuste_inventario_id,
            'silenciosa' => $silenciosa,
            'badge_texto' => 'Stock Reintegrado',
            'badge_tipo' => 'success',
            'badge_color' => '#10B981',
            'badge_icono' => 'check_circle',
            'flujo_origen' => "Stock: {$stockAnterior}",
            'flujo_destino' => "Stock: {$stockActual}",
            'flujo_indicador' => "+{$cantStr} UND",
            'accion_texto' => 'Ver Producto',
            'accion_url' => "/api/inventario/productos/{$producto->ProductoId}",
        ]);
    }

    /**
     * Listar notificaciones con paginación y contadores
     */
    public function listar(array $filtros = [], int $perPage = 15): array
    {
        $query = Notificacion::activas()
            ->paraUsuario($filtros['usuario_id'] ?? null)
            ->orderBy('NotificacionFecha', 'desc');

        if (!empty($filtros['tipo'])) {
            $query->where('NotificacionTipo', $filtros['tipo']);
        }

        if (isset($filtros['solo_no_leidas']) && filter_var($filtros['solo_no_leidas'], FILTER_VALIDATE_BOOLEAN)) {
            $query->where('NotificacionLeida', 'N');
        }

        $totalNoLeidas = Notificacion::noLeidas()
            ->paraUsuario($filtros['usuario_id'] ?? null)
            ->count();

        $paginator = $query->paginate($perPage);

        return [
            'notificaciones' => $paginator,
            'total_no_leidas' => $totalNoLeidas,
        ];
    }

    /**
     * Marcar notificación como leída
     */
    public function marcarLeida(string $id): bool
    {
        $notif = Notificacion::where('NotificacionId', $id)->first();
        if (!$notif) {
            return false;
        }

        return (bool) $notif->update(['NotificacionLeida' => 'S']);
    }

    /**
     * Marcar todas como leídas
     */
    public function marcarTodasLeidas(?string $usuarioId = null): int
    {
        $query = Notificacion::noLeidas()->paraUsuario($usuarioId);
        return $query->update(['NotificacionLeida' => 'S']);
    }

    /**
     * Obtener eventos recientes directamente de la base de datos para el streaming SSE
     */
    public function obtenerEventosRecientes(int $limit = 20): array
    {
        return Notificacion::activas()
            ->orderBy('NotificacionFecha', 'desc')
            ->take($limit)
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->NotificacionId,
                    'titulo' => $n->NotificacionTitulo,
                    'mensaje' => $n->NotificacionMensaje,
                    'tipo' => $n->NotificacionTipo,
                    'silenciosa' => $n->NotificacionSilenciosa === 'S',
                    'fecha' => $n->NotificacionFecha ? $n->NotificacionFecha->toIso8601String() : null,
                    'tarjeta' => $n->NotificacionDatos,
                ];
            })
            ->all();
    }
}
