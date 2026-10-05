<?php

namespace App\Http\Resources;

use App\Support\AuditHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrdenCompraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Mapeo canónico a los 5 estados oficiales solicitados:
        // EMITIDA, RECEPCION_PARCIAL, CERRADA_CONFORME, CERRADA_CON_FALTANTE, ANULADA
        $estadoCanonico = match ($this->Orden_CompraEstado) {
            'EMITIDA', 'BORRADOR', 'ENVIADA', 'PENDIENTE_RECEPCION', 'P' => 'EMITIDA',
            'RECEPCION_PARCIAL', 'EN_RECEPCION' => 'RECEPCION_PARCIAL',
            'CERRADA_CONFORME', 'CERRADA', 'C' => ($this->Orden_CompraCerradaConFaltante === 'S' ? 'CERRADA_CON_FALTANTE' : 'CERRADA_CONFORME'),
            'CERRADA_CON_FALTANTE' => 'CERRADA_CON_FALTANTE',
            'ANULADA', 'A', 'CANCELADA_PROVEEDOR' => 'ANULADA',
            default => $this->Orden_CompraEstado ?? 'EMITIDA'
        };

        $estadoTexto = match ($estadoCanonico) {
            'EMITIDA' => 'Emitida',
            'RECEPCION_PARCIAL' => 'En Recepción Parcial',
            'CERRADA_CONFORME' => 'Cerrada Conforme',
            'CERRADA_CON_FALTANTE' => 'Cerrada con Faltante',
            'ANULADA' => 'Anulada',
            default => $estadoCanonico
        };

        return [
            'id' => $this->Orden_CompraId,
            'fecha' => $this->Orden_CompraFecha?->toIso8601String(),
            'fecha_formateada' => $this->Orden_CompraFecha?->format('d/m/Y H:i'),
            'fecha_emision' => $this->Orden_CompraFecha?->format('d/m/Y H:i'),
            'fecha_estimada_llegada' => $this->Orden_CompraFechaEstimadaLlegada?->toIso8601String(),
            'fecha_estimada_llegada_formateada' => $this->Orden_CompraFechaEstimadaLlegada?->format('d/m/Y'),
            'fecha_entrega_estimada' => $this->Orden_CompraFechaEstimadaLlegada?->format('d/m/Y'),
            'fecha_recepcion_real' => $this->Orden_CompraFechaRecepcionReal?->toIso8601String(),
            'fecha_recepcion_real_formateada' => $this->Orden_CompraFechaRecepcionReal?->format('d/m/Y H:i'),
            'subtotal' => (float) $this->Orden_CompraSubtotal,
            'igv' => (float) $this->Orden_CompraIgv,
            'total' => (float) $this->Orden_CompraTotal,
            'estado' => $estadoCanonico,
            'estado_original' => $this->Orden_CompraEstado,
            'estado_texto' => $estadoTexto,
            'observacion' => $this->Orden_CompraObservacion,
            'motivo_anulacion' => $this->Orden_CompraMotivoAnulacion,
            'usuario_recepcion_id' => $this->Orden_CompraUsuarioRecepcionId,
            'usuario_receptor' => AuditHelper::resolverNombreUsuario($this->Orden_CompraUsuarioRecepcionId),
            'cerrada_con_faltante' => $this->Orden_CompraCerradaConFaltante === 'S',
            'proveedor_id' => $this->Orden_Compra_ProveedorId,
            'proveedor' => $this->whenLoaded('proveedor', function () {
                if (!$this->proveedor) return null;
                return [
                    'id' => $this->proveedor->ProveedorId,
                    'ruc' => $this->proveedor->ProveedorRuc,
                    'razon_social' => $this->proveedor->ProveedorRazonSocial,
                    'telefono' => $this->proveedor->ProveedorTelefono,
                    'tipo_contribuyente' => $this->proveedor->ProveedorTipoContribuyente,
                    'actividad_economica' => $this->proveedor->ProveedorActividadEconomica,
                    'estado' => $this->proveedor->ProveedorEstado,
                ];
            }),
            'detalles' => DetalleOrdenCompraResource::collection($this->whenLoaded('detalles')),
            'total_items' => $this->relationLoaded('detalles') ? $this->detalles->count() : null,
            'total_unidades' => $this->relationLoaded('detalles') ? (float) $this->detalles->sum('Detalle_Orden_CompraCantidad') : null,
            'usuario_creador' => AuditHelper::resolverNombreUsuario($this->Orden_CompraUsuarioCreacion),
            'auditoria' => [
                'usuario_creacion' => $this->Orden_CompraUsuarioCreacion,
                'usuario_creacion_nombre' => AuditHelper::resolverNombreUsuario($this->Orden_CompraUsuarioCreacion),
                'fecha_creacion' => $this->Orden_CompraFechaCreacion?->toIso8601String(),
                'usuario_modificacion' => $this->Orden_CompraUsuarioModificacion,
                'usuario_modificacion_nombre' => AuditHelper::resolverNombreUsuario($this->Orden_CompraUsuarioModificacion),
                'fecha_modificacion' => $this->Orden_CompraFechaModificacion?->toIso8601String(),
            ],
        ];
    }
}
