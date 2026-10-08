<?php

namespace App\Http\Requests\Pedido;

use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\PedidoTemporal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El bloque "unico" es identico al que existia antes del pago dividido:
     * el flujo de un solo metodo no cambia en nada.
     */
    public function rules(): array
    {
        return $this->esDividido() ? $this->reglasDividido() : $this->reglasUnico();
    }

    /**
     * Compatibilidad: un cliente viejo (o Postman con el payload anterior)
     * no envia "modo". Se asume pago unico.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->filled('modo')) {
            $this->merge(['modo' => PedidoTemporal::MODO_UNICO]);
        }
    }

    public function esDividido(): bool
    {
        return $this->input('modo') === PedidoTemporal::MODO_DIVIDIDO;
    }

    private function reglasUnico(): array
    {
        return [
            'modo'        => ['required', Rule::in([PedidoTemporal::MODO_UNICO, PedidoTemporal::MODO_DIVIDIDO])],
            'mpa_codigo'  => ['required', Rule::in($this->codigosValidos())],
            'comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    /**
     * min:2 porque el pago dividido arranca siempre con Pago 1 y Pago 2.
     * Si el cliente quiere pagar con uno solo, el modo correcto es "unico".
     *
     * El monto se valida como entero: el guarani no usa decimales, y
     * permitir centimos haria que la suma nunca cierre exacta contra el
     * total del pedido.
     */
    private function reglasDividido(): array
    {
        return [
            'modo'                => ['required', Rule::in([PedidoTemporal::MODO_UNICO, PedidoTemporal::MODO_DIVIDIDO])],
            'pagos'               => ['required', 'array', 'min:2', 'max:10'],
            'pagos.*.mpa_codigo'  => ['required', Rule::in($this->codigosValidos())],
            'pagos.*.monto' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'pagos.*.comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'pagos.*.conservar'   => ['nullable', 'boolean'],
        ];
    }

    /**
     * Exigencia del comprobante por pago. Depende solo del catalogo
     * metodo_pago, por eso vive aca y no en el Service.
     *
     * "conservar" significa: este pago ya tenia un comprobante cargado en el
     * borrador y el cliente no lo cambio. El Service verifica que ese archivo
     * exista realmente antes de darlo por valido.
     */
    public function withValidator(Validator $validator): void
    {
        if (!$this->esDividido()) {
            return;
        }

        $validator->after(function (Validator $v) {
            if ($v->errors()->hasAny(['pagos', 'pagos.*'])) {
                return;
            }

            $requiere = MetodoPago::activos()->pluck('mpa_requcom', 'mpa_codigo');

            foreach ((array) $this->input('pagos', []) as $i => $pago) {
                $codigo = $pago['mpa_codigo'] ?? null;

                if (!$codigo || !($requiere[$codigo] ?? false)) {
                    continue;
                }

                $tieneArchivo   = $this->hasFile("pagos.$i.comprobante");
                $quiereAnterior = filter_var($pago['conservar'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if (!$tieneArchivo && !$quiereAnterior) {
                    $v->errors()->add(
                        "pagos.$i.comprobante",
                        'Adjuntá el comprobante del Pago ' . ($i + 1) . '.',
                    );
                }
            }
        });
    }

    private function codigosValidos(): array
    {
        return [
            MetodoPago::TRANSFERENCIA,
            MetodoPago::BILLETERA_MOVIL,
            MetodoPago::CONTRAENTREGA,
        ];
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

            'pagos.required' => 'Cargá los pagos con los que vas a cubrir el total.',
            'pagos.min'      => 'El pago dividido requiere al menos dos pagos.',
            'pagos.max'      => 'No podés dividir el pedido en más de 10 pagos.',

            'pagos.*.mpa_codigo.required'  => 'Elegí el método de cada pago.',
            'pagos.*.mpa_codigo.in'        => 'Uno de los métodos seleccionados no es válido.',
            'pagos.*.monto.required'       => 'Ingresá el monto de cada pago.',
            'pagos.*.monto.integer'        => 'El monto debe ser un número entero en guaraníes.',
            'pagos.*.monto.min'            => 'El monto de cada pago debe ser mayor que 0.',
            'pagos.*.comprobante.file'     => 'El comprobante debe ser un archivo válido.',
            'pagos.*.comprobante.mimes'    => 'El comprobante debe ser una imagen (jpg, png) o PDF.',
            'pagos.*.comprobante.max'      => 'El comprobante no puede superar los 5 MB.',
            'pagos.*.monto.max' => 'El monto de cada pago es demasiado alto.',
        ];
    }
}