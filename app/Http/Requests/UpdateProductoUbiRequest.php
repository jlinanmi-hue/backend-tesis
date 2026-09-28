<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductoUbiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'producto_ubi_descripcion' => [
                'sometimes',
                'required',
                'string',
                'max:100',
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
        ];
    }
}
