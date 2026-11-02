<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos del modal «Validar comprobante».
 *
 * confirma_monto es obligatorio y tiene que venir en true: el admin
 * declara que el comprobante cubre el monto asignado a ese pago. No es un
 * tramite -- ese monto es el que se declara a la DNIT, y una factura
 * declarada solo se corrige con nota de credito.
 *
 * El monto NO viaja en el payload: se lee de pedido_pago. Si llegara del
 * cliente, bastaria con alterarlo para declarar cualquier importe.
 */
class ValidarComprobanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirma_monto' => ['accepted'],
            'observacion'    => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirma_monto.accepted' => 'Confirmá que el comprobante cubre el monto asignado a este pago.',
            'observacion.max'         => 'La observación no puede superar los 255 caracteres.',
        ];
    }
}