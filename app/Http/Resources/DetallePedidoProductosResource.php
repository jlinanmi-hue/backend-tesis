<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetallePedidoProductosResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->Detalle_Pedido_ProductosId,
            'pedido_id' => $this->Detalle_Pedido_Productos_PedidoId,
            'producto_id' => $this->Detalle_Pedido_Productos_ProductoId,
            'producto_nombre' => $this->producto?->ProductoNombre,
            'producto_codigo' => $this->producto?->ProductoCodigo,
            'producto_marca' => $this->producto?->ProductoMarca,
            'producto_descripcion' => $this->producto?->producto_descripcion,
            'producto_imagen' => $this->producto?->producto_imagen,
            'unidad_id' => $this->Detalle_Pedido_Productos_unidades_medidaId,
            'unidad_nombre' => $this->unidadMedida?->unidades_medidaDescripcionUnidades ?? 'Unidad',
            'unidad_abreviatura' => $this->unidadMedida?->unidades_medidaAbreviatura ?? 'UND',
            'factor_conversion' => (float) ($this->Detalle_Pedido_Productos_factor_conversion ?? 1.0),
            'cantidad' => (float) $this->Detalle_Pedido_Productos_cantidad,
            'cantidad_base' => (float) ($this->Detalle_Pedido_Productos_cantidad_base ?? ($this->Detalle_Pedido_Productos_cantidad * ($this->Detalle_Pedido_Productos_factor_conversion ?? 1.0))),
            'cantidad_fisica' => (float) ($this->Detalle_Pedido_Productos_cantidad_fisica ?? $this->Detalle_Pedido_Productos_cantidad),
            'cantidad_virtual' => (float) ($this->Detalle_Pedido_Productos_cantidad_virtual ?? 0),
            'es_virtual' => (float) ($this->Detalle_Pedido_Productos_cantidad_virtual ?? 0) > 0,
            'precio_unitario' => (float) $this->Detalle_Pedido_Productos_precio_unitario_venta,
            'subtotal' => (float) $this->Detalle_Pedido_Productos_subtotal,
        ];
    }
}
