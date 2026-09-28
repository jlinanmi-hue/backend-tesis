<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => [
                'required',
                'string',
            ],
            'system_instruction' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'enable_tools' => [
                'nullable',
                'boolean',
            ],
            'temperature' => [
                'nullable',
                'numeric',
                'between:0,2',
            ],
            'max_tokens' => [
                'nullable',
                'integer',
                'min:1',
                'max:8192',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'prompt.required' => 'El campo prompt es obligatorio.',
            'prompt.string' => 'El prompt debe ser una cadena de texto.',
            'system_instruction.max' => 'La instrucción del sistema no puede superar los 2000 caracteres.',
            'temperature.between' => 'La temperatura debe estar entre 0 y 2.',
        ];
    }
}
