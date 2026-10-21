<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetallePedidoProductosResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tuvoRotura = ($this->Detalle_Pedido_ProductosTuvoRotura === 'S');
        $cantFaltante = (float) ($this->Detalle_Pedido_ProductosCantidadFaltanteRotura ?? 0);
        $cantActual = (float) $this->Detalle_Pedido_Productos_cantidad;
        $cantDespachada = (float) ($this->Detalle_Pedido_Productos_cantidad_despachada ?? $cantActual);
        $cantSolicitada = ($tuvoRotura && $cantFaltante > 0)
            ? round($cantActual + $cantFaltante, 2)
            : $cantActual;

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
            'cantidad' => $cantActual,
            'cantidad_solicitada' => $cantSolicitada,
            'cantidad_despachada' => $cantDespachada,
            'cantidad_faltante' => $cantFaltante,
            'tuvo_rotura' => $tuvoRotura,
            'estado_item_despacho' => $this->Detalle_Pedido_Productos_estado_item_despacho ?? 'VERIFICADO_OK',
            'observacion_item' => $this->Detalle_Pedido_Productos_observacion_item,
            'cantidad_base' => (float) ($this->Detalle_Pedido_Productos_cantidad_base ?? ($cantActual * ($this->Detalle_Pedido_Productos_factor_conversion ?? 1.0))),
            'cantidad_fisica' => (float) ($this->Detalle_Pedido_Productos_cantidad_fisica ?? $cantActual),
            'precio_unitario' => (float) $this->Detalle_Pedido_Productos_precio_unitario_venta,
            'subtotal' => (float) $this->Detalle_Pedido_Productos_subtotal,
        ];
    }
}
