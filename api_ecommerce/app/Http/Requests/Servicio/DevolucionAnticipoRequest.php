<?php

namespace App\Http\Requests\Servicio;

use App\Models\Pedido\MetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Devolución del dinero de un anticipo. La referencia es obligatoria en
 * transferencia y giro: lo decide el service (mpa_requcom).
 */
class DevolucionAnticipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mpa_codigo'      => ['required', Rule::in([
                MetodoPago::CONTRAENTREGA, MetodoPago::TRANSFERENCIA, MetodoPago::BILLETERA_MOVIL,
            ])],
            'monto'           => ['required', 'integer', 'min:1', 'max:999999999999'],
            'nro_transaccion' => ['nullable', 'string', 'max:50'],
            'banco'           => ['nullable', 'string', 'max:60'],
            'fecha'           => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'observacion'     => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'mpa_codigo.required'   => 'Seleccioná cómo se devuelve el dinero.',
            'mpa_codigo.in'         => 'La forma de devolución no es válida.',
            'monto.required'        => 'Ingresá el monto devuelto.',
            'monto.integer'         => 'El monto se expresa en guaraníes, sin decimales.',
            'monto.min'             => 'El monto debe ser mayor que cero.',
            'nro_transaccion.max'   => 'La referencia no puede superar los 50 caracteres.',
            'banco.max'             => 'El banco o billetera no puede superar los 60 caracteres.',
            'fecha.before_or_equal' => 'La fecha de la devolución no puede ser posterior a hoy.',
            'observacion.max'       => 'La observación no puede superar los 255 caracteres.',
        ];
    }
}