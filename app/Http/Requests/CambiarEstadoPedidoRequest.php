<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CambiarEstadoPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => 'required|string|in:P,C,A,p,c,a,Pendiente,Completada,Cancelada,Anulada',
            'motivo' => 'nullable|string|max:250',
            'estado_despacho' => 'nullable|string|in:ENTREGADO_COMPLETO,ENTREGADO_PARCIAL,RECHAZADO,DEVUELTO,PENDIENTE',
            'causa_fallo_despacho' => 'nullable|string|in:FALTA_STOCK,ERROR_DIRECCION,ERROR_FACTURACION,RECHAZO_CLIENTE,PROBLEMA_LOGISTICO,OTRO',
            'usuario_despacho' => 'nullable|string|max:50',
            'tiene_error' => 'nullable|string|in:S,N,s,n',
            'tipo_error' => 'nullable|string|max:50',
            'severidad_error' => 'nullable|string|in:LEVE,MODERADO,CRITICO',
            'costo_error' => 'nullable|numeric|min:0',
            'momento_deteccion_error' => 'nullable|string|in:PRE_DESPACHO,POST_DESPACHO',
            'origen_deteccion_error' => 'nullable|string|in:DETECTADO_HUMANO,DETECTADO_IA',
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'El nuevo estado del pedido es obligatorio.',
            'estado.in' => 'El estado debe ser P (Pendiente), C (Completada) o A (Cancelada/Anulada).',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Error de validación al cambiar el estado del pedido.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
