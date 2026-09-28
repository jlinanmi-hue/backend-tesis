<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoOrdenCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', 'in:P,C,A,p,c,a'],
            'motivo' => ['nullable', 'string', 'max:250'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado debe ser P (Pendiente), C (Completada) o A (Anulada).',
        ];
    }
}
