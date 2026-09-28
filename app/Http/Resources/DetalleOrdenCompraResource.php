<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetalleOrdenCompraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->Detalle_Orden_CompraId,
            'orden_compra_id' => $this->Detalle_Orden_Compra_Orden_CompraId,
            'producto_id' => $this->Detalle_ProductoId,
            'producto_nombre' => $this->producto?->ProductoNombre,
            'producto_marca' => $this->producto?->ProductoMarca,
            'producto_descripcion' => $this->producto?->producto_descripcion,
            'producto_imagen' => $this->producto?->producto_imagen,
            'unidad_medida_id' => $this->Detalle_UnidadMedidaId,
            'unidad_medida_nombre' => $this->unidadMedida?->unidades_medidaDescripcionUnidades,
            'unidad_medida_abreviatura' => $this->unidadMedida?->unidades_medidaAbreviatura,
            'cantidad' => (float) $this->Detalle_Orden_CompraCantidad,
            'precio_unitario' => (float) $this->Detalle_Orden_CompraPrecioUnitario,
            'subtotal' => (float) $this->Detalle_Orden_CompraSubtotal,
        ];
    }
}
