<?php

namespace App\Http\Requests\Servicio;

use App\Http\Requests\Servicio\Concerns\ValidaChequeServicio;
use App\Models\Pedido\MetodoPago;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Anticipo web: UN pago por transferencia/billetera (con comprobante) o
 * cheque (con sus datos). El tope lo valida PagoServicioService.
 */
class AnticipoServicioRequest extends FormRequest
{
    use ValidaChequeServicio;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $codigos = [MetodoPago::TRANSFERENCIA, MetodoPago::BILLETERA_MOVIL];

        if ($this->chequeHabilitado()) {
            $codigos[] = MetodoPago::CHEQUE;
        }

        return [
            'mpa_codigo'  => ['required', Rule::in($codigos)],
            'monto'       => ['required', 'integer', 'min:1', 'max:999999999999'],
            'comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ] + $this->reglasCheque('cheque.');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->has('mpa_codigo')) {
                return;
            }

            if ($this->esCodigoCheque($this->input('mpa_codigo'))) {
                $this->verificarCheque($v, (array) $this->input('cheque', []), 'cheque');
                return;
            }

            if (!$this->hasFile('comprobante')) {
                $v->errors()->add('comprobante', 'Adjuntá el comprobante del pago.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'mpa_codigo.required' => 'Elegí cómo vas a pagar el anticipo.',
            'mpa_codigo.in'       => 'El anticipo se paga por transferencia, billetera o cheque.',
            'monto.required'      => 'Ingresá el monto del anticipo.',
            'monto.integer'       => 'El monto se expresa en guaraníes, sin decimales.',
            'monto.min'           => 'El anticipo debe ser mayor que cero.',
            'comprobante.mimes'   => 'El comprobante debe ser una imagen (jpg, png) o PDF.',
            'comprobante.max'     => 'El comprobante no puede superar los 5 MB.',
        ] + $this->mensajesCheque('cheque.');
    }
}