<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => [
                'required',
                'string',
            ],
            'history' => [
                'nullable',
                'array',
            ],
            'history.*.role' => [
                'required_with:history',
                'string',
                'in:user,model',
            ],
            'history.*.text' => [
                'required_with:history',
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
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'El mensaje es obligatorio.',
            'history.array' => 'El historial de conversación debe ser un arreglo de mensajes.',
            'history.*.role.in' => 'El rol de cada mensaje debe ser "user" o "model".',
        ];
    }
}
