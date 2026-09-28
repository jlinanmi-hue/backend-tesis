<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdatePedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'acuerdo_comercial' => 'nullable|string|max:45',
            'PedidoAcuerdo_Comercial' => 'nullable|string|max:45',
            'canal_id' => 'nullable|string|exists:Canal_pedido,Canal_pedidoId',
            'Pedido_canal_pedidoId' => 'nullable|string|exists:Canal_pedido,Canal_pedidoId',
            'motivo_correccion' => 'nullable|string|max:255',
            'tiene_error' => 'nullable|string|in:S,N,s,n',
            'tipo_error' => 'nullable|string|max:50',
            'severidad_error' => 'nullable|string|in:LEVE,MODERADO,CRITICO',
            'costo_error' => 'nullable|numeric|min:0',
            'acciones' => 'nullable|array',
            'producto_id' => 'nullable|string',
            'cantidad' => 'nullable|numeric|min:0.01',
            'accion' => 'nullable|string',
            'tipo_accion' => 'nullable|string',
            'unidad_id' => 'nullable|string',
            'factor_conversion' => 'nullable|numeric',
            'precio_unitario' => 'nullable|numeric',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Error de validación al actualizar el pedido.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
