<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAjusteInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Ajuste_inventario_ProductoId' => [
                'required',
                'string',
                'exists:Producto,ProductoId',
            ],
            'Ajuste_inventario_tipo' => [
                'required',
                'string',
                Rule::in(['Merma', 'Dañado', 'Vencido', 'Rotura', 'Pérdida', 'Ajuste Físico']),
            ],
            'Ajuste_inventario_cantidad' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'Ajuste_inventario_motivo' => [
                'required',
                'string',
                'max:255',
            ],
            'Ajuste_inventario_fecha' => [
                'nullable',
                'date',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'Ajuste_inventario_ProductoId.required' => 'El producto es obligatorio.',
            'Ajuste_inventario_ProductoId.exists' => 'El producto seleccionado no existe.',
            'Ajuste_inventario_tipo.required' => 'El tipo de ajuste es obligatorio.',
            'Ajuste_inventario_tipo.in' => 'El tipo debe ser: Merma, Dañado, Vencido, Rotura, Pérdida o Ajuste Físico.',
            'Ajuste_inventario_cantidad.required' => 'La cantidad es obligatoria.',
            'Ajuste_inventario_cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'Ajuste_inventario_motivo.required' => 'El motivo o descripción del ajuste es obligatorio.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if (empty($input['Ajuste_inventario_ProductoId'])) {
            if (!empty($input['producto_id'])) {
                $input['Ajuste_inventario_ProductoId'] = trim($input['producto_id']);
            } elseif (!empty($input['productoId'])) {
                $input['Ajuste_inventario_ProductoId'] = trim($input['productoId']);
            } elseif (!empty($input['ProductoId'])) {
                $input['Ajuste_inventario_ProductoId'] = trim($input['ProductoId']);
            }
        }

        if (empty($input['Ajuste_inventario_tipo']) && !empty($input['tipo'])) {
            $input['Ajuste_inventario_tipo'] = trim($input['tipo']);
        }

        if (!isset($input['Ajuste_inventario_cantidad']) && isset($input['cantidad'])) {
            $input['Ajuste_inventario_cantidad'] = $input['cantidad'];
        }

        if (empty($input['Ajuste_inventario_motivo'])) {
            if (!empty($input['motivo'])) {
                $input['Ajuste_inventario_motivo'] = trim($input['motivo']);
            } elseif (!empty($input['descripcion'])) {
                $input['Ajuste_inventario_motivo'] = trim($input['descripcion']);
            } elseif (!empty($input['observacion'])) {
                $input['Ajuste_inventario_motivo'] = trim($input['observacion']);
            }
        }

        if (empty($input['Ajuste_inventario_fecha']) && !empty($input['fecha'])) {
            $input['Ajuste_inventario_fecha'] = trim($input['fecha']);
        }

        $this->replace($input);
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Errores de validación en el registro de ajuste de inventario.',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
