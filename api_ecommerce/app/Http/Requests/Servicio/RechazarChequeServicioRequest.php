<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Modal [Rechazar cheque] del servicio. A diferencia del pedido, NO cancela
 * la solicitud: rechaza ese pago y el saldo vuelve a quedar pendiente.
 */
class RechazarChequeServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_id' => ['required', 'integer', 'exists:motivo_rechazo_pago,mrp_id'],
            'detalle'   => ['nullable', 'string', 'max:300'],
            'confirma'  => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_id.required' => 'Seleccioná el motivo del rechazo.',
            'motivo_id.exists'   => 'El motivo de rechazo seleccionado no existe.',
            'confirma.accepted'  => 'El rechazo deja ese pago sin efecto y el saldo vuelve a quedar pendiente. Confirmá para continuar.',
            'detalle.max'        => 'El detalle no puede superar los 300 caracteres.',
        ];
    }
}