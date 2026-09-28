<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreatePedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => 'required_without:Pedido_ClienteId|string|exists:Cliente,ClienteId',
            'Pedido_ClienteId' => 'nullable|string|exists:Cliente,ClienteId',
            'canal_id' => 'nullable|string|exists:Canal_pedido,Canal_pedidoId',
            'Pedido_canal_pedidoId' => 'nullable|string|exists:Canal_pedido,Canal_pedidoId',
            'acuerdo_comercial' => 'nullable|string|max:45',
            'PedidoAcuerdo_Comercial' => 'nullable|string|max:45',
            'origen_ia' => 'nullable|string|in:S,N,s,n',
            'PedidoOrigenIA' => 'nullable|string|in:S,N,s,n',
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|string|exists:Producto,ProductoId',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.precio_unitario' => 'nullable|numeric|min:0',
            'detalles.*.unidad_id' => 'nullable|string',
            'detalles.*.factor_conversion' => 'nullable|numeric|min:0.01',
            'tiempo_registro_segundos' => 'nullable|integer|min:0',
            'tiempo_seleccion_cliente_seg' => 'nullable|integer|min:0',
            'tiempo_agregado_productos_seg' => 'nullable|integer|min:0',
            'tiempo_validacion_stock_seg' => 'nullable|integer|min:0',
            'tiempo_confirmacion_seg' => 'nullable|integer|min:0',
            'tiempo_seleccion_cliente_ms' => 'nullable|integer|min:0',
            'tiempo_carga_productos_ms' => 'nullable|integer|min:0',
            'tiempo_validacion_stock_ms' => 'nullable|integer|min:0',
            'tiempo_confirmacion_pago_ms' => 'nullable|integer|min:0',
            'tiempo_pausas_inactivo_seg' => 'nullable|integer|min:0',
            'tiempo_activo_segundos' => 'nullable|integer|min:0',
            'intentos_correccion_formulario' => 'nullable|integer|min:0',
            'tipo_cliente_registro' => 'nullable|string|in:NUEVO,RECURRENTE',
            'dispositivo_registro' => 'nullable|string|in:DESKTOP,TABLET,MOVIL,DESCONOCIDO',
            'origen_ia_confianza' => 'nullable|numeric|between:0,100',
            'modelo_ia_utilizado' => 'nullable|string|max:50',
            'telemetria' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required_without' => 'Debe seleccionar un cliente válido para el pedido.',
            'cliente_id.exists' => 'El cliente seleccionado no existe o está inactivo.',
            'detalles.required' => 'El pedido debe contener al menos un producto en el detalle.',
            'detalles.min' => 'El pedido debe contener al menos un producto.',
            'detalles.*.producto_id.required' => 'El ID de cada producto es obligatorio.',
            'detalles.*.producto_id.exists' => 'Uno de los productos seleccionados no existe.',
            'detalles.*.cantidad.required' => 'La cantidad a pedir es obligatoria.',
            'detalles.*.cantidad.min' => 'La cantidad de cada producto debe ser mayor a 0.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Error de validación en los datos del pedido.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
