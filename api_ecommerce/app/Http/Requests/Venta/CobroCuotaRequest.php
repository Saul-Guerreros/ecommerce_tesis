<?php

namespace App\Http\Requests\Venta;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos del cobro de una o varias cuotas en un solo recibo.
 *
 * NO lleva monto: sin pagos parciales, el total lo arma el service
 * sumando las cuotas. Aceptarlo del cliente abriria la puerta a cobrar
 * un importe distinto al de la deuda.
 */
class CobroCuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // el permiso lo aplica el middleware de la ruta
    }

    public function rules(): array
    {
        return [
            'cuotas'     => ['required', 'array', 'min:1'],
            'cuotas.*'   => ['integer', 'distinct', 'exists:cta_cobrar,cco_id'],

            'rec_mpaid'  => ['required', 'integer', 'exists:metodo_pago,mpa_id'],
            'rec_fecha'  => ['required', 'date', 'before_or_equal:today'],
            'rec_nrotrx' => ['nullable', 'string', 'max:50'],
            'rec_banco'  => ['nullable', 'string', 'max:60'],
            'rec_observ' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'cuotas.required'    => 'Seleccioná al menos una cuota para cobrar.',
            'cuotas.*.exists'    => 'Alguna de las cuotas seleccionadas no existe.',
            'rec_mpaid.required' => 'Indicá con qué método de pago se cobró.',
            'rec_fecha.required' => 'La fecha del cobro es obligatoria.',
            'rec_fecha.before_or_equal' => 'La fecha del cobro no puede ser futura.',
        ];
    }
}