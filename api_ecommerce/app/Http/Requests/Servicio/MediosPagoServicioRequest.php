<?php

namespace App\Http\Requests\Servicio;

use App\Http\Requests\Servicio\Concerns\ValidaChequeServicio;
use App\Models\Pedido\MetodoPago;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Medios que el cajero DECLARA antes de emitir una factura (anticipo o
 * saldo). CHEQUE en el mostrador: datos completos obligatorios (incluido el
 * número), porque el cajero tiene el papel y el cheque nace RECIBIDO.
 */
abstract class MediosPagoServicioRequest extends FormRequest
{
    use ValidaChequeServicio;

    /** Anticipo: obligatorios. Factura final: opcionales (puede bastar lo web). */
    protected bool $mediosObligatorios = false;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $codigos = [MetodoPago::CONTRAENTREGA, MetodoPago::TRANSFERENCIA, MetodoPago::BILLETERA_MOVIL];

        if ($this->chequeHabilitado()) {
            $codigos[] = MetodoPago::CHEQUE;
        }

        return [
            'pagos'              => [$this->mediosObligatorios ? 'required' : 'nullable', 'array', 'min:1', 'max:5'],
            'pagos.*.mpa_codigo' => ['required', Rule::in($codigos)],
            'pagos.*.monto'      => ['required', 'integer', 'min:1', 'max:999999999999'],
        ] + $this->reglasCheque('pagos.*.cheque.');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->hasAny(['pagos', 'pagos.*.mpa_codigo'])) {
                return;
            }

            foreach ((array) $this->input('pagos', []) as $i => $pago) {
                if ($this->esCodigoCheque($pago['mpa_codigo'] ?? null)) {
                    $this->verificarCheque($v, (array) ($pago['cheque'] ?? []), "pagos.$i.cheque", ' del medio ' . ($i + 1));
                }
            }
        });
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
        ] + $this->mensajesCheque('pagos.*.cheque.');
    }
}