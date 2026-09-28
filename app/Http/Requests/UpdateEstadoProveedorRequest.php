<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEstadoProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => [
                'required',
                'string',
                'size:1',
                Rule::in(['A', 'I']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'El estado es obligatorio.',
            'estado.string' => 'El estado debe ser una cadena de texto.',
            'estado.size' => 'El estado debe contener exactamente 1 carácter.',
            'estado.in' => 'El estado solo acepta A (Activo) o I (Inactivo).',
        ];
    }
}
