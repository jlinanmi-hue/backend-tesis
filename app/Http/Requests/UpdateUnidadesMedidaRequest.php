<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnidadesMedidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unidadId = $this->route('id') ?? $this->route('unidades_medida') ?? $this->route('unidad') ?? $this->id;

        return [
            'unidades_medidaDescripcionUnidades' => [
                'sometimes',
                'required',
                'string',
                'max:45',
                Rule::unique('unidades_medida', 'unidades_medidaDescripcionUnidades')->ignore($unidadId, 'unidades_medidaId'),
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
            'unidades_medidaDescripcionUnidades.required' => 'La descripción no puede estar vacía.',
            'unidades_medidaDescripcionUnidades.string' => 'La descripción debe ser una cadena de texto.',
            'unidades_medidaDescripcionUnidades.max' => 'La descripción no puede superar los 45 caracteres.',
            'unidades_medidaDescripcionUnidades.unique' => 'La unidad de medida ingresada ya pertenece a otro registro.',
            'unidades_medidaEstadoUnidades.size' => 'El estado debe tener exactamente 1 carácter.',
            'unidades_medidaEstadoUnidades.in' => 'El estado solo acepta A (Activo) o I (Inactivo).',
        ];
    }
}
