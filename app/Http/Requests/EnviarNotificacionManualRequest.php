<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnviarNotificacionManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enviado_por' => ['required', 'string', 'exists:Usuario,UsuarioId'],

            'destinatarios' => ['required', 'array'],
            'destinatarios.usuarios' => ['nullable', 'array'],
            'destinatarios.usuarios.*' => ['string', 'exists:Usuario,UsuarioId'],
            'destinatarios.roles' => ['nullable', 'array'],
            'destinatarios.roles.*' => ['string', Rule::exists('Rol_User', 'Rol_UserEmpleadocol')
                ->where('Rol_UserEliminado', 'N')],
            'destinatarios.todos' => ['nullable', 'boolean'],

            'motivo' => ['nullable', 'string', Rule::in(array_keys(config('notificaciones.motivos', [])))],
            'titulo' => ['required', 'string', 'min:3', 'max:100'],
            'mensaje' => ['required', 'string', 'min:5', 'max:500'],
            'prioridad' => ['nullable', 'string', Rule::in(config('notificaciones.prioridades', ['NORMAL']))],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $destinatarios = $this->input('destinatarios', []);
            $todos = filter_var($destinatarios['todos'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (!$todos && empty($destinatarios['usuarios']) && empty($destinatarios['roles'])) {
                $validator->errors()->add(
                    'destinatarios',
                    'Selecciona al menos un usuario, un rol o la opción "todos los usuarios".'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'enviado_por.required' => 'No se pudo identificar al usuario que envía la notificación.',
            'enviado_por.exists' => 'El usuario remitente no existe.',

            'destinatarios.required' => 'Debes seleccionar al menos un destinatario.',
            'destinatarios.usuarios.*.exists' => 'Uno de los usuarios seleccionados no existe.',
            'destinatarios.roles.*.exists' => 'Uno de los roles seleccionados no existe o está inactivo.',

            'titulo.required' => 'El título es obligatorio.',
            'titulo.min' => 'El título debe tener al menos 3 caracteres.',
            'titulo.max' => 'El título no puede superar los 100 caracteres.',

            'mensaje.required' => 'El mensaje es obligatorio.',
            'mensaje.min' => 'El mensaje debe tener al menos 5 caracteres.',
            'mensaje.max' => 'El mensaje no puede superar los 500 caracteres.',

            'motivo.in' => 'El motivo seleccionado no es válido.',
            'prioridad.in' => 'La prioridad debe ser NORMAL, ALTA o URGENTE.',
        ];
    }
}
