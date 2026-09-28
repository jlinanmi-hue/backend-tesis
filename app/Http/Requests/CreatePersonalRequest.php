<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePersonalRequest extends FormRequest
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
            'EmpleadoNombres' => [
                'required',
                'string',
                'max:45',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u',
            ],
            'EmpleadoApellidos' => [
                'required',
                'string',
                'max:45',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u',
            ],
            'EmpleadoDni' => [
                'required',
                'string',
                'regex:/^[0-9]{8}$/',
                'unique:Empleado,EmpleadoDni',
            ],
            'EmpleadoTelefono' => [
                'nullable',
                'string',
                'regex:/^[0-9]+$/',
                'max:45',
            ],
            'EmpleadoCorreo' => [
                'required',
                'email',
                'max:45',
                'unique:Empleado,EmpleadoCorreo',
            ],
            'EmpleadoSexo' => [
                'required',
                'string',
                'size:1',
                Rule::in(['M', 'F']),
            ],
            'Empleado_cargoId' => [
                'required',
                'string',
                'max:20',
                'exists:cargo,cargoId',
            ],
            'EmpleadoFechaIngreso' => [
                'required',
                'date_format:Y-m-d',
            ],
            'EmpleadoEstado' => [
                'nullable',
                'string',
                'size:1',
                Rule::in(['A', 'V', 'L', 'I', 'S']),
            ],
            'rolUserId' => [
                'nullable',
                'string',
                'max:20',
                'exists:Rol_User,Rol_UserId',
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
            'EmpleadoNombres.required' => 'El campo nombres es obligatorio.',
            'EmpleadoNombres.string' => 'El campo nombres debe ser una cadena de texto.',
            'EmpleadoNombres.max' => 'El campo nombres no puede superar los 45 caracteres.',
            'EmpleadoNombres.regex' => 'El campo nombres solo debe contener letras, espacios, tildes y la letra ñ.',

            'EmpleadoApellidos.required' => 'El campo apellidos es obligatorio.',
            'EmpleadoApellidos.string' => 'El campo apellidos debe ser una cadena de texto.',
            'EmpleadoApellidos.max' => 'El campo apellidos no puede superar los 45 caracteres.',
            'EmpleadoApellidos.regex' => 'El campo apellidos solo debe contener letras, espacios, tildes y la letra ñ.',

            'EmpleadoDni.required' => 'El DNI es obligatorio.',
            'EmpleadoDni.regex' => 'El DNI debe tener exactamente 8 dígitos numéricos.',
            'EmpleadoDni.unique' => 'El DNI ingresado ya se encuentra registrado en el sistema.',

            'EmpleadoTelefono.regex' => 'El teléfono solo debe contener números.',
            'EmpleadoTelefono.max' => 'El teléfono no puede superar los 45 caracteres.',

            'EmpleadoCorreo.required' => 'El correo electrónico es obligatorio.',
            'EmpleadoCorreo.email' => 'Debe ingresar un formato de correo electrónico válido.',
            'EmpleadoCorreo.max' => 'El correo electrónico no puede superar los 45 caracteres.',
            'EmpleadoCorreo.unique' => 'El correo electrónico ya se encuentra registrado en el sistema.',

            'EmpleadoSexo.required' => 'El sexo es obligatorio.',
            'EmpleadoSexo.size' => 'El campo sexo debe tener exactamente 1 carácter.',
            'EmpleadoSexo.in' => 'El campo sexo solo acepta los valores M (Masculino) o F (Femenino).',

            'Empleado_cargoId.required' => 'El cargo es obligatorio.',
            'Empleado_cargoId.max' => 'El identificador de cargo no puede superar los 20 caracteres.',
            'Empleado_cargoId.exists' => 'El cargo seleccionado no existe en la tabla cargo.',

            'EmpleadoFechaIngreso.required' => 'La fecha de ingreso es obligatoria.',
            'EmpleadoFechaIngreso.date_format' => 'La fecha de ingreso debe tener el formato válido año-mes-día (AAAA-MM-DD).',

            'EmpleadoEstado.size' => 'El estado debe tener exactamente 1 carácter.',
            'EmpleadoEstado.in' => 'El estado solo acepta: A (Activo), V (Vacaciones), L (Licencia), I (Inactivo), S (Suspendido).',

            'rolUserId.max' => 'El identificador de rol no puede superar los 20 caracteres.',
            'rolUserId.exists' => 'El rol seleccionado no existe en la tabla Rol_User.',
        ];
    }
}
