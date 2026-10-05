<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrdenCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Orden_CompraId' => ['nullable', 'string', 'max:20', 'unique:Orden_Compra,Orden_CompraId'],
            'Orden_CompraFecha' => ['nullable', 'date'],
            'Orden_CompraObservacion' => ['nullable', 'string', 'max:250'],
            'Orden_Compra_ProveedorId' => ['nullable', 'string', 'max:20', 'exists:Proveedor,ProveedorId'],
            'proveedor_id' => ['nullable', 'string', 'max:20', 'exists:Proveedor,ProveedorId'],
            'Orden_CompraEstado' => ['nullable', 'string', 'in:EMITIDA,RECEPCION_PARCIAL,CERRADA_CONFORME,CERRADA_CON_FALTANTE,ANULADA,BORRADOR,ENVIADA,PENDIENTE_RECEPCION,EN_RECEPCION,CERRADA,CANCELADA_PROVEEDOR,emitida,recepcion_parcial,cerrada_conforme,cerrada_con_faltante,anulada,P,C,A,p,c,a'],
            'estado' => ['nullable', 'string', 'in:EMITIDA,RECEPCION_PARCIAL,CERRADA_CONFORME,CERRADA_CON_FALTANTE,ANULADA,BORRADOR,ENVIADA,PENDIENTE_RECEPCION,EN_RECEPCION,CERRADA,CANCELADA_PROVEEDOR,emitida,recepcion_parcial,cerrada_conforme,cerrada_con_faltante,anulada,P,C,A,p,c,a'],
            'Orden_CompraFechaEstimadaLlegada' => ['nullable', 'date'],
            'fecha_estimada_llegada' => ['nullable', 'date'],
            'fecha_entrega_estimada' => ['nullable', 'date'],
            'Orden_CompraIgv' => ['nullable', 'numeric', 'min:0'],

            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.Detalle_ProductoId' => ['required', 'string', 'max:20', 'exists:Producto,ProductoId'],
            'detalles.*.Detalle_UnidadMedidaId' => ['nullable', 'string', 'max:20', 'exists:unidades_medida,unidades_medidaId'],
            'detalles.*.Detalle_Orden_CompraCantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.Detalle_Orden_CompraPrecioUnitario' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'detalles.required' => 'Debe ingresar al menos un producto en la orden de compra.',
            'detalles.min' => 'La orden debe contener al menos un producto.',
            'detalles.*.Detalle_ProductoId.required' => 'El código de producto es obligatorio en cada detalle.',
            'detalles.*.Detalle_ProductoId.exists' => 'Uno de los productos seleccionados no existe en el sistema.',
            'detalles.*.Detalle_Orden_CompraCantidad.gt' => 'La cantidad debe ser mayor a cero.',
            'detalles.*.Detalle_Orden_CompraPrecioUnitario.min' => 'El precio unitario no puede ser negativo.',
            'Orden_Compra_ProveedorId.exists' => 'El proveedor seleccionado no existe.',
        ];
    }

    /**
     * Mapear nombres alternativos antes de validar
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if (empty($input['Orden_Compra_ProveedorId']) && !empty($input['proveedor_id'])) {
            $input['Orden_Compra_ProveedorId'] = $input['proveedor_id'];
        }

        if (empty($input['Orden_CompraFechaEstimadaLlegada'])) {
            if (!empty($input['fecha_entrega_estimada'])) {
                $input['Orden_CompraFechaEstimadaLlegada'] = $input['fecha_entrega_estimada'];
            } elseif (!empty($input['fecha_estimada_llegada'])) {
                $input['Orden_CompraFechaEstimadaLlegada'] = $input['fecha_estimada_llegada'];
            }
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
