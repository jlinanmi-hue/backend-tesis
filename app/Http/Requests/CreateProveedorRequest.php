<?php

namespace App\Http\Requests;

use App\Rules\RucPeruanoRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ProveedorRuc' => [
                'required',
                'string',
                new RucPeruanoRule(true),
                Rule::unique('Proveedor', 'ProveedorRuc')->where(function ($query) {
                    return $query->where('ProveedorRuc', '!=', '10000000000')
                                 ->where('ProveedorRuc', '!=', '00000000000');
                }),
            ],
            'ProveedorRazonSocial' => [
                'required',
                'string',
                'max:45',
            ],
            'ProveedorTipoContribuyente' => [
                'nullable',
                'string',
                'max:45',
            ],
            'ProveedorActividadEconomica' => [
                'nullable',
                'string',
                'max:45',
            ],
            'ProveedorTelefono' => [
                'nullable',
                'string',
                'regex:/^[0-9+\s\-()]+$/',
                'max:45',
            ],
            'ProveedorEstado' => [
                'nullable',
                'string',
                'size:1',
                Rule::in(['A', 'I']),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if (empty($input['ProveedorRuc']) && !empty($input['ruc'])) {
            $input['ProveedorRuc'] = trim($input['ruc']);
        }
        if (empty($input['ProveedorRazonSocial'])) {
            if (!empty($input['razon_social'])) {
                $input['ProveedorRazonSocial'] = trim($input['razon_social']);
            } elseif (!empty($input['nombre'])) {
                $input['ProveedorRazonSocial'] = trim($input['nombre']);
            }
        }
        if (empty($input['ProveedorTelefono']) && !empty($input['telefono'])) {
            $input['ProveedorTelefono'] = trim($input['telefono']);
        }
        if (empty($input['ProveedorTipoContribuyente']) && !empty($input['tipo_contribuyente'])) {
            $input['ProveedorTipoContribuyente'] = trim($input['tipo_contribuyente']);
        }
        if (empty($input['ProveedorActividadEconomica']) && !empty($input['actividad_economica'])) {
            $input['ProveedorActividadEconomica'] = trim($input['actividad_economica']);
        }
        if (empty($input['ProveedorEstado']) && !empty($input['estado'])) {
            $input['ProveedorEstado'] = strtoupper(trim($input['estado']));
        }

        $this->replace($input);
    }

    public function messages(): array
    {
        return [
            'ProveedorRuc.required' => 'El número de RUC es obligatorio.',
            'ProveedorRuc.regex' => 'El RUC debe tener exactamente 11 dígitos numéricos.',
            'ProveedorRuc.unique' => 'El número de RUC ingresado ya se encuentra registrado.',

            'ProveedorRazonSocial.required' => 'La razón social del proveedor es obligatoria.',
            'ProveedorRazonSocial.string' => 'La razón social debe ser una cadena de texto.',
            'ProveedorRazonSocial.max' => 'La razón social no puede superar los 45 caracteres.',

            'ProveedorTipoContribuyente.max' => 'El tipo de contribuyente no puede superar los 45 caracteres.',
            'ProveedorActividadEconomica.max' => 'La actividad económica no puede superar los 45 caracteres.',

            'ProveedorTelefono.regex' => 'El teléfono solo debe contener números.',
            'ProveedorTelefono.max' => 'El teléfono no puede superar los 45 caracteres.',

            'ProveedorEstado.size' => 'El estado debe tener exactamente 1 carácter.',
            'ProveedorEstado.in' => 'El estado solo acepta A (Activo) o I (Inactivo).',
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Errores de validación en los datos del proveedor.',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
