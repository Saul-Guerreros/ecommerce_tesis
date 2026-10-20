<?php

namespace App\Http\Requests\Pedido;

use App\Models\Pedido\MetodoPago;
use App\Services\Pedido\PagoPedidoService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Paso 3 del checkout: el cliente declara con qué pagó un pedido que ya
 * existe.
 *
 * Reemplaza a CheckoutPagoRequest. Diferencia principal: desaparece la
 * bandera "conservar". Antes el pago vivía en un borrador al que el
 * cliente podía volver, así que había que distinguir "subí un archivo
 * nuevo" de "dejá el que ya tenía". Ahora el pago se declara una sola vez
 * y queda escrito en pedido_pago: o viene el archivo, o falta.
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
        return [
            'modo'        => ['required', Rule::in($this->modosValidos())],
            'mpa_codigo'  => ['required', Rule::in($this->codigosValidos())],
            'comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    /**
     * min:2 porque el pago dividido arranca siempre con Pago 1 y Pago 2.
     * Si el cliente quiere pagar con uno solo, el modo correcto es "unico".
     *
     * El monto se valida como entero: el guaraní no usa decimales, y
     * permitir céntimos haría que la suma nunca cierre exacta contra el
     * total del pedido.
     */
    private function reglasDividido(): array
    {
        return [
            'modo'                => ['required', Rule::in($this->modosValidos())],
            'pagos'               => ['required', 'array', 'min:2', 'max:10'],
            'pagos.*.mpa_codigo'  => ['required', Rule::in($this->codigosValidos())],
            'pagos.*.monto'       => ['required', 'integer', 'min:1', 'max:999999999999'],
            'pagos.*.comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    /**
     * Exigencia del comprobante. Depende sólo del catálogo metodo_pago
     * (mpa_requcom), por eso vive acá y no en el Service: es una regla de
     * los datos enviados, no del estado del sistema.
     *
     * El efectivo contra entrega nunca lleva comprobante.
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

                if (!$codigo || !($requiere[$codigo] ?? false)) {
                    continue;
                }

                if (!$this->hasFile("pagos.$i.comprobante")) {
                    $v->errors()->add(
                        "pagos.$i.comprobante",
                        'Adjuntá el comprobante del Pago ' . ($i + 1) . '.',
                    );
                }
            }
        });
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