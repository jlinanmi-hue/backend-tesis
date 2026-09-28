<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoriaId = $this->route('id') ?? $this->route('categoria') ?? $this->id;

        return [
            'Categoria_ProductoDescripcion_categoria' => [
                'sometimes',
                'required',
                'string',
                'max:45',
                Rule::unique('Categoria_Producto', 'Categoria_ProductoDescripcion_categoria')->ignore($categoriaId, 'Categoria_ProductoId'),
            ],
            'Categoria_ProductoEstado' => [
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
            'Categoria_ProductoDescripcion_categoria.required' => 'La descripción no puede estar vacía.',
            'Categoria_ProductoDescripcion_categoria.string' => 'La descripción debe ser una cadena de texto.',
            'Categoria_ProductoDescripcion_categoria.max' => 'La descripción no puede superar los 45 caracteres.',
            'Categoria_ProductoDescripcion_categoria.unique' => 'La categoría ingresada ya pertenece a otra categoría registrada.',
            'Categoria_ProductoEstado.size' => 'El estado debe tener exactamente 1 carácter.',
            'Categoria_ProductoEstado.in' => 'El estado solo acepta A (Activo) o I (Inactivo).',
        ];
    }
}
