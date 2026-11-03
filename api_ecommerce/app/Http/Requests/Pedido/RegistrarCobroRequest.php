<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos que el administrador carga en el modal «Realizar cobro».
 *
 * Reemplaza a ConfirmarPagoRequest: ahora estos datos no describen la
 * verificacion de un pago sino el cobro que se registra contra la factura
 * (cobro_venta).
 *
 * El monto esperado NO viaja en el payload: se lee de pedido_pago en el
 * service. Si llegara del cliente, bastaria con alterarlo para que la
 * verificacion de diferencia nunca saltara.
 */
class RegistrarCobroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Guaranies: sin decimales. min:1 replica el CHECK de la tabla.
            'monto_verificado' => ['required', 'integer', 'min:1', 'max:999999999999'],

            'nro_transaccion' => ['nullable', 'string', 'max:50'],
            'banco'           => ['nullable', 'string', 'max:60'],

            // No se acepta una fecha de cobro futura. Si no viene, el
            // service usa hoy: cob_fecha es NOT NULL.
            'fecha_pago'  => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'observacion' => ['nullable', 'string', 'max:255'],

            // El administrador reconoce que el monto no coincide.
            'confirmar_diferencia' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'monto_verificado.required' => 'Ingresá el monto que verificaste en el comprobante.',
            'monto_verificado.integer'  => 'El monto se expresa en guaraníes, sin decimales.',
            'monto_verificado.min'      => 'El monto verificado debe ser mayor que cero.',

            'nro_transaccion.max' => 'El N° de transacción no puede superar los 50 caracteres.',
            'banco.max'           => 'El nombre del banco o billetera no puede superar los 60 caracteres.',

            'fecha_pago.date_format'     => 'La fecha del cobro no tiene un formato válido.',
            'fecha_pago.before_or_equal' => 'La fecha del cobro no puede ser posterior a hoy.',
            'observacion.max'            => 'La observación no puede superar los 255 caracteres.',
        ];
    }
}