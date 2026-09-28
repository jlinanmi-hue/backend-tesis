<?php

namespace App\Http\Requests;

use App\Rules\RucPeruanoRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ClienteNombre' => [
                'required',
                'string',
                'max:100',
            ],
            'ClienteDireccion' => [
                'required',
                'string',
                'max:200',
            ],
            'ClienteNumero' => [
                'required',
                'string',
                'regex:/^[0-9+\s\-()]+$/',
                'max:45',
            ],
            'ClienteRuc' => [
                'required',
                'string',
                new RucPeruanoRule(true),
                Rule::unique('Cliente', 'ClienteRuc')->where(function ($query) {
                    return $query->whereNotIn('ClienteRuc', ['10000000000', '00000000000', '11110000', '00000000']);
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
            'ClienteNombre.required' => 'El nombre o razón social del cliente es obligatorio.',
            'ClienteNombre.string' => 'El nombre del cliente debe ser una cadena de texto.',
            'ClienteNombre.max' => 'El nombre del cliente no puede superar los 100 caracteres.',

            'ClienteDireccion.required' => 'La dirección del cliente es obligatoria.',
            'ClienteDireccion.string' => 'La dirección debe ser una cadena de texto.',
            'ClienteDireccion.max' => 'La dirección no puede superar los 200 caracteres.',

            'ClienteNumero.required' => 'El teléfono de contacto es obligatorio.',
            'ClienteNumero.regex' => 'El teléfono de contacto solo debe contener números.',
            'ClienteNumero.max' => 'El teléfono no puede superar los 45 caracteres.',

            'ClienteRuc.required' => 'El RUC o DNI del cliente es obligatorio.',
            'ClienteRuc.string' => 'El RUC/DNI debe ser una cadena de texto.',
            'ClienteRuc.max' => 'El RUC/DNI no puede superar los 45 caracteres.',
            'ClienteRuc.unique' => 'El RUC/DNI ingresado ya se encuentra registrado en el sistema.',

            'ClienteEstado.size' => 'El estado debe tener exactamente 1 carácter.',
            'ClienteEstado.in' => 'El estado solo acepta A (Activo) o I (Inactivo).',
        ];
    }
}
