<?php

namespace App\Http\Requests;

use App\Rules\RucPeruanoRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clienteId = $this->route('id') ?? $this->route('cliente') ?? $this->id;

        return [
            'ClienteNombre' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'ClienteDireccion' => [
                'sometimes',
                'required',
                'string',
                'max:200',
            ],
            'ClienteNumero' => [
                'sometimes',
                'required',
                'string',
                'regex:/^[0-9+\s\-()]+$/',
                'max:45',
            ],
            'ClienteRuc' => [
                'sometimes',
                'required',
                'string',
                new RucPeruanoRule(true),
                Rule::unique('Cliente', 'ClienteRuc')->ignore($clienteId, 'ClienteId')->where(function ($query) {
                    return $query->where('ClienteRuc', '!=', '10000000000')
                                 ->where('ClienteRuc', '!=', '00000000000');
                }),
            ],
            'ClienteEstado' => [
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
            'ClienteNombre.required' => 'El nombre no puede estar vacío.',
            'ClienteNombre.string' => 'El nombre del cliente debe ser una cadena de texto.',
            'ClienteNombre.max' => 'El nombre del cliente no puede superar los 100 caracteres.',

            'ClienteDireccion.required' => 'La dirección no puede estar vacía.',
            'ClienteDireccion.string' => 'La dirección debe ser una cadena de texto.',
            'ClienteDireccion.max' => 'La dirección no puede superar los 200 caracteres.',

            'ClienteNumero.required' => 'El teléfono de contacto no puede estar vacío.',
            'ClienteNumero.regex' => 'El teléfono de contacto solo debe contener números.',
            'ClienteNumero.max' => 'El teléfono no puede superar los 45 caracteres.',

            'ClienteRuc.required' => 'El RUC/DNI no puede estar vacío.',
            'ClienteRuc.string' => 'El RUC/DNI debe ser una cadena de texto.',
            'ClienteRuc.max' => 'El RUC/DNI no puede superar los 45 caracteres.',
            'ClienteRuc.unique' => 'El RUC/DNI ingresado ya pertenece a otro cliente registrado.',

            'ClienteEstado.size' => 'El estado debe tener exactamente 1 carácter.',
            'ClienteEstado.in' => 'El estado solo acepta A (Activo) o I (Inactivo).',
        ];
    }
}
