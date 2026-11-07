<?php

namespace App\Http\Requests\Servicio;

/**
 * A5 "Cobrar y entregar": datos de la entrega (EntregaServicioRequest) + el
 * bloque "cobro" del único pago pendiente. El medio y el monto no viajan:
 * salen del pago declarado en la factura final. Sin pago pendiente (ya
 * cobrado, o final cubierta por el anticipo), "cobro" puede omitirse.
 */
class CobrarEntregarRequest extends EntregaServicioRequest
{
    public function rules(): array
    {
        return parent::rules()
            + ['cobro' => ['nullable', 'array']]
            + RegistrarCobroServicioRequest::reglasCobro('cobro.');
    }

    public function messages(): array
    {
        return parent::messages() + RegistrarCobroServicioRequest::mensajesCobro('cobro.');
    }
}