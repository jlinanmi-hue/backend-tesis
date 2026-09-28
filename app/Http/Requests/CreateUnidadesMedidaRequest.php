<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUnidadesMedidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unidades_medidaDescripcionUnidades' => [
                'required',
                'string',
                'max:45',
                'unique:unidades_medida,unidades_medidaDescripcionUnidades',
            ],
            'unidades_medidaAbreviatura' => [
                'nullable',
                'string',
                'max:10',
            ],
            'unidades_medidaEsBase' => [
                'nullable',
                'boolean',
            ],
            'unidades_medidaEstadoUnidades' => [
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
            'unidades_medidaDescripcionUnidades.required' => 'La descripción de la unidad de medida es obligatoria.',
            'unidades_medidaDescripcionUnidades.string' => 'La descripción debe ser una cadena de texto.',
            'unidades_medidaDescripcionUnidades.max' => 'La descripción no puede superar los 45 caracteres.',
            'unidades_medidaDescripcionUnidades.unique' => 'La unidad de medida ingresada ya se encuentra registrada.',
            'unidades_medidaEstadoUnidades.size' => 'El estado debe tener exactamente 1 carácter.',
            'unidades_medidaEstadoUnidades.in' => 'El estado solo acepta A (Activo) o I (Inactivo).',
        ];
    }
}
