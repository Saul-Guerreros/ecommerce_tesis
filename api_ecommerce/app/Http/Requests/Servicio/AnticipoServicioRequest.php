<?php

namespace App\Http\Requests\Servicio;

use App\Models\Pedido\MetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Anticipo web: un solo pago por transferencia o billetera, con comprobante.
 * El tope (presupuesto aprobado) lo valida PagoServicioService.
 */
class AnticipoServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mpa_codigo'  => ['required', Rule::in([MetodoPago::TRANSFERENCIA, MetodoPago::BILLETERA_MOVIL])],
            'monto'       => ['required', 'integer', 'min:1', 'max:999999999999'],
            'comprobante' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'mpa_codigo.required'  => 'Elegí cómo vas a pagar el anticipo.',
            'mpa_codigo.in'        => 'El anticipo se paga por transferencia o billetera.',
            'monto.required'       => 'Ingresá el monto del anticipo.',
            'monto.integer'        => 'El monto se expresa en guaraníes, sin decimales.',
            'monto.min'            => 'El anticipo debe ser mayor que cero.',
            'comprobante.required' => 'Adjuntá el comprobante del pago.',
            'comprobante.mimes'    => 'El comprobante debe ser una imagen (jpg, png) o PDF.',
            'comprobante.max'      => 'El comprobante no puede superar los 5 MB.',
        ];
    }
}