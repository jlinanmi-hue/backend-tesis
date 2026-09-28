<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEstadoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'estado' => [
                'required',
                'string',
                'size:1',
                Rule::in(['A', 'V', 'L', 'I', 'S']),
            ],
            'motivo' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'El estado es obligatorio.',
            'estado.string' => 'El estado debe ser una cadena de texto.',
            'estado.size' => 'El estado debe contener exactamente 1 carácter.',
            'estado.in' => 'El estado solo acepta los siguientes valores: A (Activo), V (Vacaciones), L (Licencia), I (Inactivo), S (Suspendido).',
            'motivo.string' => 'El motivo debe ser una cadena de texto.',
            'motivo.max' => 'El motivo no puede superar los 255 caracteres.',
        ];
    }
}
