<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos del modal [Rechazar cheque].
 *
 * Que el motivo pertenezca al ámbito CHEQUE lo valida el service: depende
 * de la fila del catálogo, no del payload.
 */
class RechazarChequeRequest extends FormRequest
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
            'confirma.accepted'  => 'El rechazo cancela el pedido y libera el stock reservado. Confirmá para continuar.',
            'detalle.max'        => 'El detalle no puede superar los 300 caracteres.',
        ];
    }
}