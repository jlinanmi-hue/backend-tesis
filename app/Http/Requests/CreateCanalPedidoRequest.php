<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCanalPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Canal_pedidoDescripcion' => [
                'required',
                'string',
                'max:45',
                'unique:Canal_pedido,Canal_pedidoDescripcion',
            ],
            'Canal_pedidoEstado' => [
                'nullable',
                'string',
                'size:1',
                Rule::in(['A', 'I']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'Canal_pedidoDescripcion.required' => 'La descripción del canal de pedido es obligatoria.',
            'Canal_pedidoDescripcion.string' => 'La descripción debe ser una cadena de texto.',
            'Canal_pedidoDescripcion.max' => 'La descripción no puede superar los 45 caracteres.',
            'Canal_pedidoDescripcion.unique' => 'El canal de pedido ingresado ya se encuentra registrado.',
            'Canal_pedidoEstado.size' => 'El estado debe tener exactamente 1 carácter.',
            'Canal_pedidoEstado.in' => 'El estado solo acepta A (Activo) o I (Inactivo).',
        ];
    }
}
