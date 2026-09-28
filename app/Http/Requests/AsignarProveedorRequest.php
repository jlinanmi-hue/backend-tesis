<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AsignarProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proveedor_id' => ['required', 'string', 'max:20', 'exists:Proveedor,ProveedorId'],
        ];
    }

    public function messages(): array
    {
        return [
            'proveedor_id.required' => 'Debe seleccionar un proveedor para asignar a la orden.',
            'proveedor_id.exists' => 'El proveedor seleccionado no existe en el sistema.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();
        if (empty($input['proveedor_id']) && !empty($input['Orden_Compra_ProveedorId'])) {
            $input['proveedor_id'] = $input['Orden_Compra_ProveedorId'];
        }
        $this->replace($input);
    }
}
