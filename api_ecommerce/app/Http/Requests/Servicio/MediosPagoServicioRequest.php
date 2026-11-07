<?php

namespace App\Http\Requests\Servicio;

use App\Models\Pedido\MetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Medios de pago que el cajero DECLARA antes de emitir una factura
 * (anticipo o saldo). Son intenciones: el dinero se registra después con
 * "Registrar cobro". Uno o varios (pago mixto).
 */
abstract class MediosPagoServicioRequest extends FormRequest
{
    /** Anticipo: obligatorios. Factura final: opcionales (puede bastar lo web). */
    protected bool $mediosObligatorios = false;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pagos'              => [$this->mediosObligatorios ? 'required' : 'nullable', 'array', 'min:1', 'max:5'],
            'pagos.*.mpa_codigo' => ['required', Rule::in([
                MetodoPago::CONTRAENTREGA, MetodoPago::TRANSFERENCIA, MetodoPago::BILLETERA_MOVIL,
            ])],
            'pagos.*.monto'      => ['required', 'integer', 'min:1', 'max:999999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'pagos.required'              => 'Indicá al menos un medio de pago.',
            'pagos.max'                   => 'Se admiten hasta 5 medios de pago.',
            'pagos.*.mpa_codigo.required' => 'Seleccioná la forma de pago.',
            'pagos.*.mpa_codigo.in'       => 'La forma de pago no es válida.',
            'pagos.*.monto.required'      => 'Ingresá el monto de cada medio.',
            'pagos.*.monto.integer'       => 'El monto se expresa en guaraníes, sin decimales.',
            'pagos.*.monto.min'           => 'Cada monto debe ser mayor que cero.',
        ];
    }
}