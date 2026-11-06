<?php

namespace App\Http\Requests\Servicio;

use App\Models\Pedido\MetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cobro presencial (anticipo o saldo). La referencia es obligatoria para
 * transferencia y giro Tigo: lo valida CobroMostradorService porque depende
 * de metodo_pago.mpa_requcom, no del payload.
 */
class CobroMostradorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(self::reglasCobro(''), [
            'monto' => ['required', 'integer', 'min:1', 'max:999999999999'],
        ]);
    }

    /** Reglas compartidas con CobrarEntregarRequest ($prefijo = 'cobro.'). */
    public static function reglasCobro(string $prefijo): array
    {
        return [
            "{$prefijo}mpa_codigo"      => ['required', Rule::in([MetodoPago::TRANSFERENCIA, MetodoPago::BILLETERA_MOVIL, MetodoPago::CONTRAENTREGA])],
            "{$prefijo}nro_transaccion" => ['nullable', 'string', 'max:50'],
            "{$prefijo}banco"           => ['nullable', 'string', 'max:60'],
            "{$prefijo}fecha_pago"      => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            "{$prefijo}observacion"     => ['nullable', 'string', 'max:255'],
            "{$prefijo}comprobante"     => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public static function mensajesCobro(string $prefijo): array
    {
        return [
            "{$prefijo}mpa_codigo.required"      => 'Seleccione la forma de pago.',
            "{$prefijo}mpa_codigo.in"            => 'La forma de pago no es válida.',
            "{$prefijo}nro_transaccion.max"      => 'La referencia no puede superar los 50 caracteres.',
            "{$prefijo}banco.max"                => 'El banco o billetera no puede superar los 60 caracteres.',
            "{$prefijo}fecha_pago.before_or_equal" => 'La fecha del pago no puede ser posterior a hoy.',
            "{$prefijo}comprobante.mimes"        => 'El comprobante debe ser una imagen (jpg, png) o PDF.',
            "{$prefijo}comprobante.max"          => 'El comprobante no puede superar los 5 MB.',
        ];
    }

    public function messages(): array
    {
        return self::mensajesCobro('') + [
            'monto.required' => 'Ingrese el monto cobrado.',
            'monto.integer'  => 'El monto se expresa en guaraníes, sin decimales.',
            'monto.min'      => 'El monto debe ser mayor que cero.',
        ];
    }
}
