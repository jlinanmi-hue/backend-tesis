<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCanalPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $canalId = $this->route('id') ?? $this->route('canales_pedido') ?? $this->route('canal') ?? $this->id;

        return [
            'Canal_pedidoDescripcion' => [
                'sometimes',
                'required',
                'string',
                'max:45',
                Rule::unique('Canal_pedido', 'Canal_pedidoDescripcion')->ignore($canalId, 'Canal_pedidoId'),
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
            'Canal_pedidoDescripcion.required' => 'La descripción no puede estar vacía.',
            'Canal_pedidoDescripcion.string' => 'La descripción debe ser una cadena de texto.',
            'Canal_pedidoDescripcion.max' => 'La descripción no puede superar los 45 caracteres.',
            'Canal_pedidoDescripcion.unique' => 'El canal de pedido ingresado ya pertenece a otro registro.',
            'Canal_pedidoEstado.size' => 'El estado debe tener exactamente 1 carácter.',
            'Canal_pedidoEstado.in' => 'El estado solo acepta A (Activo) o I (Inactivo).',
        ];
    }
}
