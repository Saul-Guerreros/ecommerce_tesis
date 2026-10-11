<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos del modal «Rechazar pago».
 *
 * El detalle es obligatorio solo para los motivos marcados con
 * mrp_requdet, y esa regla se valida en el service porque depende de la
 * fila del catalogo, no del payload.
 */
class RechazarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_id' => ['required', 'integer', 'exists:motivo_rechazo_pago,mrp_id'],

            // Texto que VE EL CLIENTE en su sección «Mis pedidos».
            'detalle' => ['nullable', 'string', 'max:255'],

            // Lo que realmente decía el comprobante. Opcional porque no
            // todos los motivos tienen un importe asociado (p. ej. ilegible).
            'monto_verificado' => ['nullable', 'integer', 'min:1', 'max:999999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_id.required' => 'Seleccioná el motivo del rechazo.',
            'motivo_id.exists'   => 'El motivo de rechazo seleccionado no existe.',
            'detalle.max'        => 'El detalle no puede superar los 255 caracteres.',
            'monto_verificado.integer' => 'El monto se expresa en guaraníes, sin decimales.',
        ];
    }
}