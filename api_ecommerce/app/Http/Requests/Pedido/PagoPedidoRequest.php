<?php

namespace App\Http\Requests\Pedido;

use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\PagoCheque;
use App\Services\Pedido\PagoPedidoService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Configuracion\ConfiguracionTienda;


/**
 * Paso 3 del checkout: el cliente declara con qué pagó un pedido que ya
 * existe.
 *
 * El método CHEQUE no lleva archivo adjunto: su equivalente son cuatro
 * datos declarados (tipo, banco, CI y nombre del titular).
 */
class PagoPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->esDividido() ? $this->reglasDividido() : $this->reglasUnico();
    }

    public function esDividido(): bool
    {
        return $this->input('modo') === PagoPedidoService::MODO_DIVIDIDO;
    }

    private function reglasUnico(): array
    {
        $esCheque = $this->input('mpa_codigo') === MetodoPago::CHEQUE;
        $exigir   = $esCheque ? 'required' : 'nullable';

        return [
            'modo'        => ['required', Rule::in($this->modosValidos())],
            'mpa_codigo'  => ['required', Rule::in($this->codigosValidos())],
            'comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],

            'cheque'        => [$exigir, 'array'],
            'cheque.tipo'   => [$exigir, Rule::in($this->tiposChequeValidos())],
            'cheque.banco'  => [$exigir, 'string', 'max:80'],
            'cheque.nrodoc' => [$exigir, 'string', 'max:20'],
            'cheque.nombre' => [$exigir, 'string', 'max:150'],
            'cheque.nro'    => [$exigir, 'string', 'max:20'],

            // Sólo obligatoria en el diferido: la condición no se puede
            // expresar con $exigir, que depende del método y no del tipo.
            'cheque.fecha_cobro' => [
                Rule::requiredIf(fn () => $this->esCheque()
                    && $this->input('cheque.tipo') === PagoCheque::TIPO_DIFERIDO),
                'nullable', 'date', 'after_or_equal:today',
            ],
        ];
    }

    /**
     * min:2 porque el pago dividido arranca siempre con Pago 1 y Pago 2.
     *
     * Los campos del cheque van como 'nullable': cuáles son obligatorios
     * depende del método elegido en cada bloque, y eso sólo se puede
     * resolver recorriendo el array (withValidator).
     */
    private function reglasDividido(): array
    {
        return [
            'modo'                => ['required', Rule::in($this->modosValidos())],
            'pagos'               => ['required', 'array', 'min:2', 'max:10'],
            'pagos.*.mpa_codigo'  => ['required', Rule::in($this->codigosValidos())],
            'pagos.*.monto'       => ['required', 'integer', 'min:1', 'max:999999999999'],
            'pagos.*.comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],

            'pagos.*.cheque'        => ['nullable', 'array'],
            'pagos.*.cheque.tipo'   => ['nullable', Rule::in($this->tiposChequeValidos())],
            'pagos.*.cheque.banco'  => ['nullable', 'string', 'max:80'],
            'pagos.*.cheque.nrodoc' => ['nullable', 'string', 'max:20'],
            'pagos.*.cheque.nombre' => ['nullable', 'string', 'max:150'],
            'pagos.*.cheque.nro'    => ['nullable', 'string', 'max:20'],
            'pagos.*.cheque.fecha_cobro' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * Dos exigencias que dependen del catálogo y no de los datos sueltos:
     *
     *   - comprobante obligatorio si mpa_requcom (transferencia, billetera);
     *   - datos del cheque obligatorios si el método es CHEQUE.
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

    /**
     * Campos del cheque de un bloque del pago dividido. Se valida campo
     * por campo para que el cliente vea exactamente cuál le falta, en vez
     * de un único error genérico sobre todo el bloque.
    */
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

        // La fecha de cobro sólo existe en el diferido: es lo que le dice a
        // la sucursal cuándo puede depositar el papel.
        if (($cheque['tipo'] ?? null) === PagoCheque::TIPO_DIFERIDO
            && trim((string) ($cheque['fecha_cobro'] ?? '')) === '') {
            $v->errors()->add(
                "pagos.$i.cheque.fecha_cobro",
                "Ingresá la fecha de cobro del cheque diferido del Pago {$n}.",
            );
        }
    }

    /** El método elegido en el pago único es cheque. */
    private function esCheque(): bool
    {
        return $this->input('mpa_codigo') === MetodoPago::CHEQUE;
    }

    private function modosValidos(): array
    {
        return [PagoPedidoService::MODO_UNICO, PagoPedidoService::MODO_DIVIDIDO];
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

        /** Tipos que el administrador dejó habilitados en Datos de Pago. */
    private function tiposChequeValidos(): array
    {
        return ConfiguracionTienda::actual()->tiposChequeHabilitados();
    }

    public function messages(): array
    {
        return [
            'modo.required' => 'Indicá si vas a pagar con un solo método o dividido.',
            'modo.in'       => 'La forma de pago seleccionada no es válida.',

            'mpa_codigo.required' => 'Elegí un método de pago.',
            'mpa_codigo.in'       => 'El método de pago seleccionado no es válido.',
            'comprobante.file'    => 'El comprobante debe ser un archivo válido.',
            'comprobante.mimes'   => 'El comprobante debe ser una imagen (jpg, png) o PDF.',
            'comprobante.max'     => 'El comprobante no puede superar los 5 MB.',

            'cheque.required'        => 'Completá los datos del cheque.',
            'cheque.tipo.required'   => 'Elegí el tipo de cheque.',
            'cheque.tipo.in'         => 'El tipo de cheque seleccionado no es válido.',
            'cheque.banco.required'  => 'Ingresá el nombre del banco.',
            'cheque.banco.max'       => 'El nombre del banco no puede superar los 80 caracteres.',
            'cheque.nrodoc.required' => 'Ingresá la CI del titular del cheque.',
            'cheque.nrodoc.max'      => 'La CI del titular no puede superar los 20 caracteres.',
            'cheque.nombre.required' => 'Ingresá el nombre del titular del cheque.',
            'cheque.nombre.max'      => 'El nombre del titular no puede superar los 150 caracteres.',

            'cheque.nro.required'    => 'Ingresá el número del cheque.',
            'cheque.nro.max'         => 'El número del cheque no puede superar los 20 caracteres.',

            'cheque.fecha_cobro.required'       => 'Ingresá la fecha de cobro del cheque diferido.',
            'cheque.fecha_cobro.date'           => 'La fecha de cobro no es válida.',
            'cheque.fecha_cobro.after_or_equal' => 'La fecha de cobro no puede ser anterior a hoy.',

            'pagos.*.cheque.nro.max'                    => 'El número del cheque no puede superar los 20 caracteres.',
            'pagos.*.cheque.fecha_cobro.date'           => 'La fecha de cobro no es válida.',
            'pagos.*.cheque.fecha_cobro.after_or_equal' => 'La fecha de cobro no puede ser anterior a hoy.',

            'pagos.*.cheque.tipo.in'     => 'El tipo de cheque seleccionado no es válido.',
            'pagos.*.cheque.banco.max'   => 'El nombre del banco no puede superar los 80 caracteres.',
            'pagos.*.cheque.nrodoc.max'  => 'La CI del titular no puede superar los 20 caracteres.',
            'pagos.*.cheque.nombre.max'  => 'El nombre del titular no puede superar los 150 caracteres.',

            'pagos.required' => 'Cargá los pagos con los que vas a cubrir el total.',
            'pagos.min'      => 'El pago dividido requiere al menos dos pagos.',
            'pagos.max'      => 'No podés dividir el pedido en más de 10 pagos.',

            'pagos.*.mpa_codigo.required' => 'Elegí el método de cada pago.',
            'pagos.*.mpa_codigo.in'       => 'Uno de los métodos seleccionados no es válido.',
            'pagos.*.monto.required'      => 'Ingresá el monto de cada pago.',
            'pagos.*.monto.integer'       => 'El monto debe ser un número entero en guaraníes.',
            'pagos.*.monto.min'           => 'El monto de cada pago debe ser mayor que 0.',
            'pagos.*.monto.max'           => 'El monto de cada pago es demasiado alto.',
            'pagos.*.comprobante.file'    => 'El comprobante debe ser un archivo válido.',
            'pagos.*.comprobante.mimes'   => 'El comprobante debe ser una imagen (jpg, png) o PDF.',
            'pagos.*.comprobante.max'     => 'El comprobante no puede superar los 5 MB.',
        ];
    }
}