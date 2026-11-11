<?php

namespace App\Http\Requests\Servicio;

/**
 * A5 "Cobrar y entregar": datos de la entrega (EntregaServicioRequest) + el
 * bloque "cobro" del único pago pendiente. El medio y el monto no viajan:
 * salen del pago declarado en la factura final. Sin pago pendiente (ya
 * cobrado, o final cubierta por el anticipo), "cobro" puede omitirse.
 *
 * En efectivo, cobro.monto_recibido es lo que el cliente entrega: el service
 * lo exige, rechaza si no alcanza y calcula el vuelto.
 */
class CobrarEntregarRequest extends EntregaServicioRequest
{
    public function rules(): array
    {
        return parent::rules()
            + ['cobro' => ['nullable', 'array']]
            + ['cobro.monto_recibido' => ['nullable', 'integer', 'min:1', 'max:999999999999']]
            + RegistrarCobroServicioRequest::reglasCobro('cobro.');
    }

    public function messages(): array
    {
        return parent::messages()
            + RegistrarCobroServicioRequest::mensajesCobro('cobro.')
            + [
                'cobro.monto_recibido.integer' => 'El efectivo recibido se expresa en guaraníes, sin decimales.',
                'cobro.monto_recibido.min'     => 'El efectivo recibido debe ser mayor que cero.',
            ];
    }
}