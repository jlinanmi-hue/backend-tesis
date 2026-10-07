<?php

namespace App\Http\Resources;

use App\Support\AuditHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PedidoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $estado = strtoupper(trim($this->PedidoEstado_pedido ?? 'P'));
        $estadoTexto = match ($estado) {
            'P' => 'Pendiente',
            'C' => 'Completada',
            'A' => 'Cancelada / Anulada',
            default => 'Desconocido',
        };

        // Cálculo de horas para timeout
        $timeoutHoras = (int) config('orders.timeout_hours', 12);
        $fechaCreacion = $this->PedidoFechaCreacion ? \Carbon\Carbon::parse($this->PedidoFechaCreacion) : now();
        $horasTranscurridas = round($fechaCreacion->diffInMinutes(now()) / 60, 2);
        $horasRestantes = max(0, round($timeoutHoras - $horasTranscurridas, 2));
        $porExpirar = ($estado === 'P' && $horasRestantes <= 2 && $horasRestantes > 0);
        $expirado = ($estado === 'P' && $horasTranscurridas >= $timeoutHoras);

        return [
            'id' => $this->PedidoId,
            'fecha_pedido' => $this->PedidoFecha_pedido?->toIso8601String(),
            'fecha_formateada' => $this->PedidoFecha_pedido?->format('d/m/Y H:i'),
            'total' => (float) $this->PedidoTotal,
            'igv' => (float) $this->PedidoIgv,
            'subtotal' => round((float) $this->PedidoTotal - (float) $this->PedidoIgv - (float) ($this->PedidoCostoDelivery ?? 0.00), 2),
            'subtotal_productos' => round((float) $this->PedidoTotal - (float) $this->PedidoIgv - (float) ($this->PedidoCostoDelivery ?? 0.00), 2),
            'costo_delivery' => (float) ($this->PedidoCostoDelivery ?? 0.00),
            'es_delivery' => ($this->PedidoEsDelivery === 'S'),
            'fecha_entrega' => $this->PedidoFechaEntrega ? \Carbon\Carbon::parse($this->PedidoFechaEntrega)->format('Y-m-d') : null,
            'fecha_entrega_formateada' => $this->PedidoFechaEntrega ? \Carbon\Carbon::parse($this->PedidoFechaEntrega)->format('d/m/Y') : null,
            'direccion_entrega' => $this->PedidoDireccionEntrega,
            'referencia_entrega' => $this->PedidoReferenciaEntrega,
            'latitud_entrega' => $this->PedidoLatitudEntrega ? (float) $this->PedidoLatitudEntrega : null,
            'longitud_entrega' => $this->PedidoLongitudEntrega ? (float) $this->PedidoLongitudEntrega : null,
            'link_google_maps' => ($this->PedidoLatitudEntrega && $this->PedidoLongitudEntrega)
                ? "https://www.google.com/maps?q={$this->PedidoLatitudEntrega},{$this->PedidoLongitudEntrega}"
                : null,
            'link_waze' => ($this->PedidoLatitudEntrega && $this->PedidoLongitudEntrega)
                ? "https://waze.com/ul?ll={$this->PedidoLatitudEntrega},{$this->PedidoLongitudEntrega}&navigate=yes"
                : null,
            'zona_delivery_id' => $this->PedidoZonaDeliveryId,
            'zona_delivery' => $this->whenLoaded('zonaDelivery', function () {
                if (!$this->zonaDelivery) return null;
                return [
                    'id' => $this->zonaDelivery->Zona_DeliveryId,
                    'nombre' => $this->zonaDelivery->Zona_DeliveryNombre,
                    'tarifa' => (float) $this->zonaDelivery->Zona_DeliveryTarifa,
                    'estado' => $this->zonaDelivery->Zona_DeliveryEstado,
                ];
            }),
            'usuario_registro' => $this->PedidoUsuarioRegistro,
            'usuario_registro_nombre' => AuditHelper::resolverNombreUsuario($this->PedidoUsuarioRegistro),
            'usuario_creador' => AuditHelper::resolverNombreUsuario($this->PedidoUsuarioCreacion ?? $this->PedidoUsuarioRegistro),
            'estado' => $estado,
            'estado_texto' => $estadoTexto,
            'origen_ia' => ($this->PedidoOrigenIA === 'S'),
            'origen_texto' => ($this->PedidoOrigenIA === 'S') ? 'Valencia AI' : 'Manual',
            'tiene_error' => ($this->PedidoTieneError === 'S'),
            'tipo_error' => $this->PedidoTipoError,
            'acuerdo_comercial' => $this->PedidoAcuerdo_Comercial,
            'tiempo' => [
                'timeout_limite_horas' => $timeoutHoras,
                'horas_transcurridas' => $horasTranscurridas,
                'horas_restantes' => $estado === 'P' ? $horasRestantes : null,
                'por_expirar' => $porExpirar,
                'expirado' => $expirado,
            ],
            'cliente_id' => $this->Pedido_ClienteId,
            'cliente' => $this->whenLoaded('cliente', function () {
                if (!$this->cliente) return null;
                return [
                    'id' => $this->cliente->ClienteId,
                    'dni_ruc' => $this->cliente->ClienteDniRuc,
                    'nombre' => $this->cliente->ClienteNombre,
                    'telefono' => $this->cliente->ClienteTelefono,
                    'email' => $this->cliente->ClienteEmail,
                    'direccion' => $this->cliente->ClienteDireccion,
                    'tipo' => $this->cliente->ClienteTipo,
                ];
            }),
            'canal_id' => $this->Pedido_canal_pedidoId,
            'canal' => $this->whenLoaded('canalPedido', function () {
                if (!$this->canalPedido) return null;
                return [
                    'id' => $this->canalPedido->Canal_pedidoId,
                    'descripcion' => $this->canalPedido->Canal_pedidoDescripcion,
                ];
            }),
            'detalles' => DetallePedidoProductosResource::collection($this->whenLoaded('detalles')),
            'total_items' => $this->relationLoaded('detalles') ? $this->detalles->count() : null,
            'total_unidades' => $this->relationLoaded('detalles') ? (float) $this->detalles->sum('Detalle_Pedido_Productos_cantidad') : null,
            'auditoria' => [
                'usuario_creacion' => $this->PedidoUsuarioCreacion,
                'usuario_creacion_nombre' => AuditHelper::resolverNombreUsuario($this->PedidoUsuarioCreacion),
                'fecha_creacion' => $this->PedidoFechaCreacion?->toIso8601String(),
                'usuario_modificacion' => $this->PedidoUsuarioModificacion,
                'usuario_modificacion_nombre' => AuditHelper::resolverNombreUsuario($this->PedidoUsuarioModificacion),
                'fecha_modificacion' => $this->PedidoFechaModificacion?->toIso8601String(),
            ],
        ];
    }
}
