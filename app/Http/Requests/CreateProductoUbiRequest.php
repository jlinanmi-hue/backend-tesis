<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProductoUbiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'producto_ubi_descripcion' => [
                'required',
                'string',
                'max:100',
            ],
            'producto_ubi_estado' => [
                'nullable',
                'string',
                'size:1',
                Rule::in(['A', 'I']),
            ],
            'producto_ubi_observacion' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'producto_ubi_descripcion.required' => 'La descripción de la ubicación es obligatoria.',
            'producto_ubi_descripcion.max' => 'La descripción de la ubicación no puede superar los 100 caracteres.',
            'producto_ubi_estado.in' => 'El estado solo acepta A (Activo) o I (Inactivo).',
        ];
    }
}
