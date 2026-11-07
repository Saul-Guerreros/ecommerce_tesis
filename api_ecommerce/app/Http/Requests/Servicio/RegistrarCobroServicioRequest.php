<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Registrar el cobro de UN pago del servicio contra su factura.
 *
 * El medio y el monto declarado se leen de servicio_pago (no viajan). Lo
 * recibido va en monto_verificado: menor se rechaza, mayor exige
 * confirmar_diferencia. La referencia es obligatoria en transferencias de
 * mostrador: lo decide el service (depende de mpa_requcom y del canal).
 */
class RegistrarCobroServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monto_verificado'     => ['required', 'integer', 'min:1', 'max:999999999999'],
            'confirmar_diferencia' => ['nullable', 'boolean'],
        ] + self::reglasCobro('');
    }

    /** Compartidas con CobrarEntregarRequest ($prefijo = 'cobro.'). */
    public static function reglasCobro(string $prefijo): array
    {
        return [
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
            "{$prefijo}nro_transaccion.max"        => 'La referencia no puede superar los 50 caracteres.',
            "{$prefijo}banco.max"                  => 'El banco o billetera no puede superar los 60 caracteres.',
            "{$prefijo}fecha_pago.date_format"     => 'La fecha del pago no tiene un formato válido.',
            "{$prefijo}fecha_pago.before_or_equal" => 'La fecha del pago no puede ser posterior a hoy.',
            "{$prefijo}observacion.max"            => 'La observación no puede superar los 255 caracteres.',
            "{$prefijo}comprobante.mimes"          => 'El comprobante debe ser una imagen (jpg, png) o PDF.',
            "{$prefijo}comprobante.max"            => 'El comprobante no puede superar los 5 MB.',
        ];
    }

    public function messages(): array
    {
        return self::mensajesCobro('') + [
            'monto_verificado.required' => 'Ingresá el monto recibido.',
            'monto_verificado.integer'  => 'El monto se expresa en guaraníes, sin decimales.',
            'monto_verificado.min'      => 'El monto recibido debe ser mayor que cero.',
        ];
    }
}