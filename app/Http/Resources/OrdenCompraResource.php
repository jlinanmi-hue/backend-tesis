<?php

namespace App\Http\Resources;

use App\Support\AuditHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrdenCompraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $estadoTexto = match ($this->Orden_CompraEstado) {
            'P' => 'Pendiente',
            'C' => 'Completada',
            'A' => 'Anulada',
            default => 'Desconocido'
        };

        return [
            'id' => $this->Orden_CompraId,
            'fecha' => $this->Orden_CompraFecha?->toIso8601String(),
            'fecha_formateada' => $this->Orden_CompraFecha?->format('d/m/Y H:i'),
            'subtotal' => (float) $this->Orden_CompraSubtotal,
            'igv' => (float) $this->Orden_CompraIgv,
            'total' => (float) $this->Orden_CompraTotal,
            'estado' => $this->Orden_CompraEstado,
            'estado_texto' => $estadoTexto,
            'observacion' => $this->Orden_CompraObservacion,
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
