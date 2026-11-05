<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos del modal [Confirmar recepción].
 *
 * confirma_monto es el equivalente de «el comprobante cubre los Gs. X»:
 * el admin declara que el papel que tiene en la mano coincide con lo
 * registrado y está emitido por el monto asignado. Ese monto es el que se
 * factura después, así que no es un trámite.
 *
 * El monto NO viaja en el payload: se lee de pedido_pago. Si llegara del
 * cliente, bastaría con alterarlo para facturar cualquier importe.
 *
 * fecha_recepcion se autocompleta en pantalla con la hora actual, pero se
 * acepta editarla: un cheque entregado a las 18:00 puede cargarse recién
 * al día siguiente.
 */
class ConfirmarRecepcionChequeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirma_monto'  => ['accepted'],
            'fecha_recepcion' => ['nullable', 'date', 'before_or_equal:now'],
            'observacion'     => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirma_monto.accepted'        => 'Confirmá que el cheque físico coincide con lo declarado y cubre el monto asignado.',
            'fecha_recepcion.before_or_equal' => 'La fecha de recepción no puede ser futura.',
            'observacion.max'                => 'La observación no puede superar los 300 caracteres.',
        ];
    }
}