<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrdenCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Orden_CompraFecha' => ['nullable', 'date'],
            'Orden_CompraObservacion' => ['nullable', 'string', 'max:250'],
            'Orden_Compra_ProveedorId' => ['nullable', 'string', 'max:20', 'exists:Proveedor,ProveedorId'],
            'proveedor_id' => ['nullable', 'string', 'max:20', 'exists:Proveedor,ProveedorId'],

            'detalles' => ['nullable', 'array', 'min:1'],
            'detalles.*.Detalle_ProductoId' => ['required_with:detalles', 'string', 'max:20', 'exists:Producto,ProductoId'],
            'detalles.*.Detalle_UnidadMedidaId' => ['nullable', 'string', 'max:20', 'exists:unidades_medida,unidades_medidaId'],
            'detalles.*.Detalle_Orden_CompraCantidad' => ['required_with:detalles', 'numeric', 'gt:0'],
            'detalles.*.Detalle_Orden_CompraPrecioUnitario' => ['required_with:detalles', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if (empty($input['Orden_Compra_ProveedorId']) && !empty($input['proveedor_id'])) {
            $input['Orden_Compra_ProveedorId'] = $input['proveedor_id'];
        }

        if (isset($input['detalles']) && is_array($input['detalles'])) {
            foreach ($input['detalles'] as $idx => $d) {
                if (empty($d['Detalle_ProductoId']) && !empty($d['producto_id'])) {
                    $input['detalles'][$idx]['Detalle_ProductoId'] = $d['producto_id'];
                }
                if (empty($d['Detalle_UnidadMedidaId']) && !empty($d['unidad_medida_id'])) {
                    $input['detalles'][$idx]['Detalle_UnidadMedidaId'] = $d['unidad_medida_id'];
                }
                if (!isset($d['Detalle_Orden_CompraCantidad']) && isset($d['cantidad'])) {
                    $input['detalles'][$idx]['Detalle_Orden_CompraCantidad'] = $d['cantidad'];
                }
                if (!isset($d['Detalle_Orden_CompraPrecioUnitario']) && isset($d['precio_unitario'])) {
                    $input['detalles'][$idx]['Detalle_Orden_CompraPrecioUnitario'] = $d['precio_unitario'];
                }
            }
        }

        $this->replace($input);
    }
}
