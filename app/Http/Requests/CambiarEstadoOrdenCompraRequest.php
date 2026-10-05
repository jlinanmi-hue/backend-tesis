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
            'estado' => [
                'required',
                'string',
                'in:EMITIDA,RECEPCION_PARCIAL,CERRADA_CONFORME,CERRADA_CON_FALTANTE,ANULADA,emitida,recepcion_parcial,cerrada_conforme,cerrada_con_faltante,anulada,BORRADOR,ENVIADA,PENDIENTE_RECEPCION,EN_RECEPCION,CERRADA,CANCELADA_PROVEEDOR,borrador,enviada,pendiente_recepcion,en_recepcion,cerrada,cancelada_proveedor,P,C,A,p,c,a',
            ],
            'motivo' => ['nullable', 'string', 'max:250'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado ingresado no es válido para la orden de compra.',
        ];
    }
}
