<?php

namespace App\Http\Requests\Venta;

use App\Models\Configuracion\ConfiguracionTienda;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\PagoCheque;
use App\Services\Venta\CuotaPagoService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El cliente declara con que paga una o varias cuotas.
 *
 * Mismas reglas que PagoPedidoRequest (Paso 3 del checkout), mas el
 * array de cuotas: aca el cobro no se identifica por un pedido en la
 * URL sino por las cuotas que cubre.
 */
class DeclararPagoCuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $comunes = [
            'cuotas'   => ['required', 'array', 'min:1', 'max:60'],
            'cuotas.*' => ['integer', 'distinct', 'exists:cta_cobrar,cco_id'],
            'modo'     => ['required', Rule::in($this->modosValidos())],
        ];

        return $comunes + ($this->esDividido() ? $this->reglasDividido() : $this->reglasUnico());
    }

    public function esDividido(): bool
    {
        return $this->input('modo') === CuotaPagoService::MODO_DIVIDIDO;
    }

    private function reglasUnico(): array
    {
        $exigir = $this->esCheque() ? 'required' : 'nullable';

        return [
            'mpa_codigo'  => ['required', Rule::in($this->codigosValidos())],
            'comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],

            'cheque'        => [$exigir, 'array'],
            'cheque.tipo'   => [$exigir, Rule::in($this->tiposChequeValidos())],
            'cheque.banco'  => [$exigir, 'string', 'max:80'],
            'cheque.nrodoc' => [$exigir, 'string', 'max:20'],
            'cheque.nombre' => [$exigir, 'string', 'max:150'],
            'cheque.nro'    => [$exigir, 'string', 'max:20'],

            'cheque.fecha_cobro' => [
                Rule::requiredIf(fn () => $this->esCheque()
                    && $this->input('cheque.tipo') === PagoCheque::TIPO_DIFERIDO),
                'nullable', 'date', 'after_or_equal:today',
            ],
        ];
    }

    private function reglasDividido(): array
    {
        return [
            'pagos'               => ['required', 'array', 'min:2', 'max:10'],
            'pagos.*.mpa_codigo'  => ['required', Rule::in($this->codigosValidos())],
            'pagos.*.monto'       => ['required', 'integer', 'min:1', 'max:999999999999'],
            'pagos.*.comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],

            'pagos.*.cheque'             => ['nullable', 'array'],
            'pagos.*.cheque.tipo'        => ['nullable', Rule::in($this->tiposChequeValidos())],
            'pagos.*.cheque.banco'       => ['nullable', 'string', 'max:80'],
            'pagos.*.cheque.nrodoc'      => ['nullable', 'string', 'max:20'],
            'pagos.*.cheque.nombre'      => ['nullable', 'string', 'max:150'],
            'pagos.*.cheque.nro'         => ['nullable', 'string', 'max:20'],
            'pagos.*.cheque.fecha_cobro' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * Dos exigencias que dependen del catalogo y no de los datos sueltos:
     * comprobante obligatorio si mpa_requcom, y datos del cheque
     * obligatorios si el metodo es CHEQUE.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $requiere = MetodoPago::activos()->pluck('mpa_requcom', 'mpa_codigo');

            if (!$this->esDividido()) {
                $codigo = $this->input('mpa_codigo');

                if ($codigo && ($requiere[$codigo] ?? false) && !$this->hasFile('comprobante')) {
                    $v->errors()->add('comprobante', 'Este método de pago requiere adjuntar el comprobante.');
                }

                return;
            }

            if ($v->errors()->hasAny(['pagos', 'pagos.*'])) {
                return;
            }

            foreach ((array) $this->input('pagos', []) as $i => $pago) {
                $codigo = $pago['mpa_codigo'] ?? null;

                if (!$codigo) {
                    continue;
                }

                if (($requiere[$codigo] ?? false) && !$this->hasFile("pagos.$i.comprobante")) {
                    $v->errors()->add(
                        "pagos.$i.comprobante",
                        'Adjuntá el comprobante del Pago ' . ($i + 1) . '.',
                    );
                }

                if ($codigo === MetodoPago::CHEQUE) {
                    $this->verificarCheque($v, $pago['cheque'] ?? [], $i);
                }
            }
        });
    }

    /** Campo por campo, para que el cliente vea cual le falta. */
    private function verificarCheque(Validator $v, array $cheque, int $i): void
    {
        $n = $i + 1;

        $campos = [
            'tipo'   => "Elegí el tipo de cheque del Pago {$n}.",
            'banco'  => "Ingresá el nombre del banco del Pago {$n}.",
            'nrodoc' => "Ingresá la CI del titular del Pago {$n}.",
            'nombre' => "Ingresá el nombre del titular del Pago {$n}.",
            'nro'    => "Ingresá el número del cheque del Pago {$n}.",
        ];

        foreach ($campos as $campo => $mensaje) {
            if (trim((string) ($cheque[$campo] ?? '')) === '') {
                $v->errors()->add("pagos.$i.cheque.$campo", $mensaje);
            }
        }

        if (($cheque['tipo'] ?? null) === PagoCheque::TIPO_DIFERIDO
            && trim((string) ($cheque['fecha_cobro'] ?? '')) === '') {
            $v->errors()->add(
                "pagos.$i.cheque.fecha_cobro",
                "Ingresá la fecha de cobro del cheque diferido del Pago {$n}.",
            );
        }
    }

    private function esCheque(): bool
    {
        return $this->input('mpa_codigo') === MetodoPago::CHEQUE;
    }

    private function modosValidos(): array
    {
        return [CuotaPagoService::MODO_UNICO, CuotaPagoService::MODO_DIVIDIDO];
    }

    private function codigosValidos(): array
    {
        return [
            MetodoPago::TRANSFERENCIA,
            MetodoPago::BILLETERA_MOVIL,
            MetodoPago::CONTRAENTREGA,
            MetodoPago::CHEQUE,
        ];
    }

    private function tiposChequeValidos(): array
    {
        return ConfiguracionTienda::actual()->tiposChequeHabilitados();
    }

    public function messages(): array
    {
        return [
            'cuotas.required' => 'Seleccioná al menos una cuota para pagar.',

            'modo.required' => 'Indicá si vas a pagar con un solo método o dividido.',
            'modo.in'       => 'La forma de pago seleccionada no es válida.',

            'mpa_codigo.required' => 'Elegí un método de pago.',
            'mpa_codigo.in'       => 'El método de pago seleccionado no es válido.',
            'comprobante.mimes'   => 'El comprobante debe ser una imagen (jpg, png) o PDF.',
            'comprobante.max'     => 'El comprobante no puede superar los 5 MB.',

            'cheque.required'        => 'Completá los datos del cheque.',
            'cheque.tipo.required'   => 'Elegí el tipo de cheque.',
            'cheque.banco.required'  => 'Ingresá el nombre del banco.',
            'cheque.nrodoc.required' => 'Ingresá la CI del titular del cheque.',
            'cheque.nombre.required' => 'Ingresá el nombre del titular del cheque.',
            'cheque.nro.required'    => 'Ingresá el número del cheque.',
            'cheque.fecha_cobro.required'       => 'Ingresá la fecha de cobro del cheque diferido.',
            'cheque.fecha_cobro.after_or_equal' => 'La fecha de cobro no puede ser anterior a hoy.',

            'pagos.required' => 'Cargá los pagos con los que vas a cubrir el total.',
            'pagos.min'      => 'El pago dividido requiere al menos dos pagos.',
            'pagos.max'      => 'No podés dividir el pago en más de 10 partes.',

            'pagos.*.mpa_codigo.required' => 'Elegí el método de cada pago.',
            'pagos.*.monto.required'      => 'Ingresá el monto de cada pago.',
            'pagos.*.monto.min'           => 'El monto de cada pago debe ser mayor que 0.',
            'pagos.*.comprobante.mimes'   => 'El comprobante debe ser una imagen (jpg, png) o PDF.',
            'pagos.*.comprobante.max'     => 'El comprobante no puede superar los 5 MB.',
        ];
    }
}