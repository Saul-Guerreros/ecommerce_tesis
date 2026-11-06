<?php

namespace App\Http\Requests\Servicio;

use App\Models\Pedido\MetodoPago;
use App\Services\Servicio\PagoServicioService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * C4 - El cliente paga el saldo de su servicio. Mismo payload que el Paso 3
 * del checkout (CheckoutPagoRequest), así el front reutiliza paso-pago y
 * pago-dividido sin adaptar nada. Sin "conservar": acá no hay borrador.
 *
 * El monto del pago único no viaja: es el saldo que calcula el backend.
 */
class PagoServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('modo')) {
            $this->merge(['modo' => PagoServicioService::MODO_UNICO]);
        }
    }

    public function rules(): array
    {
        $modos   = [PagoServicioService::MODO_UNICO, PagoServicioService::MODO_DIVIDIDO];
        $codigos = [MetodoPago::TRANSFERENCIA, MetodoPago::BILLETERA_MOVIL, MetodoPago::CONTRAENTREGA];

        if ($this->input('modo') === PagoServicioService::MODO_DIVIDIDO) {
            return [
                'modo'                => ['required', Rule::in($modos)],
                'pagos'               => ['required', 'array', 'min:2', 'max:10'],
                'pagos.*.mpa_codigo'  => ['required', Rule::in($codigos)],
                'pagos.*.monto'       => ['required', 'integer', 'min:1', 'max:999999999999'],
                'pagos.*.comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ];
        }

        return [
            'modo'        => ['required', Rule::in($modos)],
            'mpa_codigo'  => ['required', Rule::in($codigos)],
            'comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    /** Comprobante obligatorio según metodo_pago.mpa_requcom (el efectivo no lleva). */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $requiere = MetodoPago::activos()->pluck('mpa_requcom', 'mpa_codigo');

            if ($this->input('modo') !== PagoServicioService::MODO_DIVIDIDO) {
                if (($requiere[$this->input('mpa_codigo')] ?? false) && !$this->hasFile('comprobante')) {
                    $v->errors()->add('comprobante', 'Adjuntá el comprobante del pago.');
                }
                return;
            }

            foreach ((array) $this->input('pagos', []) as $i => $pago) {
                if (($requiere[$pago['mpa_codigo'] ?? ''] ?? false) && !$this->hasFile("pagos.$i.comprobante")) {
                    $v->errors()->add("pagos.$i.comprobante", 'Adjuntá el comprobante del Pago ' . ($i + 1) . '.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'mpa_codigo.required'   => 'Elegí una forma de pago.',
            'mpa_codigo.in'         => 'La forma de pago elegida no está disponible.',
            'pagos.min'             => 'El pago dividido necesita al menos dos pagos.',
            'pagos.*.monto.integer' => 'El monto se expresa en guaraníes, sin decimales.',
            'pagos.*.monto.min'     => 'El monto de cada pago debe ser mayor que cero.',
            '*.mimes'               => 'El comprobante debe ser una imagen (jpg, png) o PDF.',
            'comprobante.mimes'     => 'El comprobante debe ser una imagen (jpg, png) o PDF.',
            'comprobante.max'       => 'El comprobante no puede superar los 5 MB.',
        ];
    }
}
