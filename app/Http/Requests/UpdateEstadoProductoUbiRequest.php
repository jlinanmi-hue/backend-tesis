<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEstadoProductoUbiRequest extends FormRequest
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
            'estado.required' => 'El campo estado es obligatorio.',
            'estado.in' => 'El estado solo acepta A (Activo) o I (Inactivo).',
        ];
    }
}
