<?php

namespace App\Http\Requests\Servicio;

/**
 * A5 "Cobrar y entregar": los datos del modal de entrega (EntregaServicioRequest)
 * + el bloque "cobro". El monto no viaja: es el saldo que calcula el backend.
 */
class CobrarEntregarRequest extends EntregaServicioRequest
{
    public function rules(): array
    {
        return parent::rules() + ['cobro' => ['required', 'array']] + CobroMostradorRequest::reglasCobro('cobro.');
    }

    public function messages(): array
    {
        return parent::messages() + CobroMostradorRequest::mensajesCobro('cobro.');
    }
}
