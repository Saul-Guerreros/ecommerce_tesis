<?php

namespace App\Http\Requests\Servicio;

use App\Http\Requests\Servicio\Concerns\ValidaChequeServicio;
use App\Models\Pedido\MetodoPago;
use App\Services\Servicio\PagoServicioService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * C4 - El cliente paga el saldo de su servicio. Mismo payload que el Paso 3
 * del checkout (PagoPedidoRequest): el front reutiliza app-pago-dividido y
 * app-cheque-form. El monto del pago único no viaja: es el saldo.
 */
class PagoServicioRequest extends FormRequest
{
    use ValidaChequeServicio;

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
        $modos = [PagoServicioService::MODO_UNICO, PagoServicioService::MODO_DIVIDIDO];

        if ($this->esDividido()) {
            return [
                'modo'                => ['required', Rule::in($modos)],
                'pagos'               => ['required', 'array', 'min:2', 'max:10'],
                'pagos.*.mpa_codigo'  => ['required', Rule::in($this->codigosValidos())],
                'pagos.*.monto'       => ['required', 'integer', 'min:1', 'max:999999999999'],
                'pagos.*.comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ] + $this->reglasCheque('pagos.*.cheque.');
        }

        return [
            'modo'        => ['required', Rule::in($modos)],
            'mpa_codigo'  => ['required', Rule::in($this->codigosValidos())],
            'comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ] + $this->reglasCheque('cheque.');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->hasAny(['modo', 'mpa_codigo', 'pagos', 'pagos.*.mpa_codigo'])) {
                return;
            }

            $requiere = MetodoPago::activos()->pluck('mpa_requcom', 'mpa_codigo');

            if (!$this->esDividido()) {
                $codigo = $this->input('mpa_codigo');

                if (($requiere[$codigo] ?? false) && !$this->hasFile('comprobante')) {
                    $v->errors()->add('comprobante', 'Adjuntá el comprobante del pago.');
                }

                if ($this->esCodigoCheque($codigo)) {
                    $this->verificarCheque($v, (array) $this->input('cheque', []), 'cheque');
                }

                return;
            }

            foreach ((array) $this->input('pagos', []) as $i => $pago) {
                $codigo = $pago['mpa_codigo'] ?? '';
                $n      = $i + 1;

                if (($requiere[$codigo] ?? false) && !$this->hasFile("pagos.$i.comprobante")) {
                    $v->errors()->add("pagos.$i.comprobante", "Adjuntá el comprobante del Pago {$n}.");
                }

                if ($this->esCodigoCheque($codigo)) {
                    $this->verificarCheque($v, (array) ($pago['cheque'] ?? []), "pagos.$i.cheque", " del Pago {$n}");
                }
            }
        });
    }

    public function esDividido(): bool
    {
        return $this->input('modo') === PagoServicioService::MODO_DIVIDIDO;
    }

    /** CHEQUE solo si el admin dejó al menos un tipo habilitado. */
    private function codigosValidos(): array
    {
        $codigos = [MetodoPago::TRANSFERENCIA, MetodoPago::BILLETERA_MOVIL, MetodoPago::CONTRAENTREGA];

        return $this->chequeHabilitado() ? [...$codigos, MetodoPago::CHEQUE] : $codigos;
    }

    public function messages(): array
    {
        return [
            'mpa_codigo.required'   => 'Elegí una forma de pago.',
            'mpa_codigo.in'         => 'La forma de pago elegida no está disponible.',
            'pagos.*.mpa_codigo.in' => 'La forma de pago elegida no está disponible.',
            'pagos.min'             => 'El pago dividido necesita al menos dos pagos.',
            'pagos.*.monto.integer' => 'El monto se expresa en guaraníes, sin decimales.',
            'pagos.*.monto.min'     => 'El monto de cada pago debe ser mayor que cero.',
            '*.mimes'               => 'El comprobante debe ser una imagen (jpg, png) o PDF.',
            'comprobante.mimes'     => 'El comprobante debe ser una imagen (jpg, png) o PDF.',
            'comprobante.max'       => 'El comprobante no puede superar los 5 MB.',
        ] + $this->mensajesCheque('cheque.') + $this->mensajesCheque('pagos.*.cheque.');
    }
}