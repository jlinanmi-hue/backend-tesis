<?php

namespace App\Http\Resources;

use App\Models\DetalleProductoMedida;
use App\Support\AuditHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetalleOrdenCompraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $factorConversion = 1;
        if (!empty($this->Detalle_ProductoId) && !empty($this->Detalle_UnidadMedidaId)) {
            if ($this->relationLoaded('producto') && $this->producto?->relationLoaded('detalleProductoMedidas')) {
                $medida = $this->producto->detalleProductoMedidas
                    ->firstWhere('Detalle_Producto_medida_unidades_medidaId', $this->Detalle_UnidadMedidaId);
                $factorConversion = $medida?->Detalle_Producto_medida_factor_conversion ?? 1;
            } else {
                $medida = DetalleProductoMedida::where('Detalle_Producto_medida_ProductoId', $this->Detalle_ProductoId)
                    ->where('Detalle_Producto_medida_unidades_medidaId', $this->Detalle_UnidadMedidaId)
                    ->where('Detalle_Producto_medidaEliminado', 'N')
                    ->first();
                $factorConversion = $medida?->Detalle_Producto_medida_factor_conversion ?? 1;
            }
        }

        if ($factorConversion <= 0) {
            $factorConversion = 1;
        }

        $cantidad = (float) $this->Detalle_Orden_CompraCantidad;
        $cantidadRecibida = (float) ($this->Detalle_Orden_CompraCantidadRecibida ?? 0.0);
        $cantidadPendiente = max(0.0, round($cantidad - $cantidadRecibida, 2));

        $abreviatura = $this->unidadMedida?->unidades_medidaAbreviatura ?? 'UND';
        $nombreUnidad = $this->unidadMedida?->unidades_medidaDescripcionUnidades ?? 'Unidad';

        $contenidoTexto = ($factorConversion > 1) ? "x {$factorConversion} UND" : "1 UND";
        $presentacionCompleta = ($factorConversion > 1) 
            ? "{$abreviatura} ({$factorConversion} UND)" 
            : $abreviatura;

        return [
            'id' => $this->Detalle_Orden_CompraId,
            'orden_compra_id' => $this->Detalle_Orden_Compra_Orden_CompraId,
            'producto_id' => $this->Detalle_ProductoId,
            'producto_nombre' => $this->producto?->ProductoNombre,
            'producto_marca' => $this->producto?->ProductoMarca,
            'producto_descripcion' => $this->producto?->producto_descripcion,
            'producto_imagen' => $this->producto?->producto_imagen,
            'unidad_medida_id' => $this->Detalle_UnidadMedidaId,
            'unidad_medida_nombre' => $nombreUnidad,
            'unidad_medida_abreviatura' => $abreviatura,
            // Aliases para compatibilidad directa con frontend
            'unidad_nombre' => $abreviatura,
            'unidad_completa' => $presentacionCompleta,
            'factor_conversion' => (int) $factorConversion,
            'contenido_texto' => $contenidoTexto,
            'presentacion_completa' => $presentacionCompleta,
            // Cantidades en la unidad de presentación solicitada
            'cantidad' => $cantidad,
            'cantidad_solicitada' => $cantidad,
            'cantidad_recibida' => $cantidadRecibida,
            'cantidad_pendiente' => $cantidadPendiente,
            // Cantidades físicas equivalentes en unidades base de almacén
            'total_unidades_base_solicitadas' => round($cantidad * $factorConversion, 2),
            'total_unidades_base_recibidas' => round($cantidadRecibida * $factorConversion, 2),
            'total_unidades_base_pendientes' => round($cantidadPendiente * $factorConversion, 2),
            'entregado' => $this->Detalle_Orden_CompraEntregado === 'S',
            'rechazado' => $this->Detalle_Orden_CompraEntregado === 'R',
            'estado_recepcion' => ($this->Detalle_Orden_CompraEntregado === 'R') 
                ? 'RECHAZADO' 
                : (($cantidadRecibida >= $cantidad) ? 'COMPLETO' : ($cantidadRecibida > 0 ? 'PARCIAL' : 'PENDIENTE')),
            'fecha_recepcion_item' => $this->Detalle_Orden_CompraFechaRecepcionItem?->toIso8601String(),
            'fecha_recepcion_item_formateada' => $this->Detalle_Orden_CompraFechaRecepcionItem?->format('d/m/Y H:i'),
            'usuario_recepcion_item_id' => $this->Detalle_Orden_CompraUsuarioRecepcionItemId,
            'usuario_recepcion_item_nombre' => AuditHelper::resolverNombreUsuario($this->Detalle_Orden_CompraUsuarioRecepcionItemId),
            'fue_producto_nuevo' => $this->Detalle_Orden_CompraFueProductoNuevo === 'S',
            'precio_unitario' => (float) $this->Detalle_Orden_CompraPrecioUnitario,
            'subtotal' => (float) $this->Detalle_Orden_CompraSubtotal,
        ];
    }
}
