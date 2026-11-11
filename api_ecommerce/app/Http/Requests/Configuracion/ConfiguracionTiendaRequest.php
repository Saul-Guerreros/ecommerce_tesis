<?php

namespace App\Http\Requests\Configuracion;

use App\Models\Configuracion\ConfiguracionTienda;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ConfiguracionTiendaRequest extends FormRequest
{
    private const CAMPOS_TIPO      = ['ctn_pago_cheque_vista', 'ctn_pago_cheque_diferido'];
    private const CAMPOS_MODALIDAD = ['ctn_pago_cheque_retiro', 'ctn_pago_cheque_envio'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ctn_pocstk_activo' => ['sometimes', 'boolean'],
            'ctn_pocstk_umbral' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000'],
            'ctn_pago_banco'            => ['sometimes', 'nullable', 'string', 'max:80'],
            'ctn_pago_banco_titular'    => ['sometimes', 'nullable', 'string', 'max:150'],
            'ctn_pago_banco_cuenta'     => ['sometimes', 'nullable', 'string', 'max:40'],
            'ctn_pago_tigo_titular'     => ['sometimes', 'nullable', 'string', 'max:150'],
            'ctn_pago_tigo_numero'      => ['sometimes', 'nullable', 'string', 'max:20'],
            'ctn_pago_tigo_referencia'  => ['sometimes', 'nullable', 'string', 'max:60'],
            'ctn_pago_efectivo_mensaje' => ['sometimes', 'nullable', 'string', 'max:200'],

            // Cheque: textos
            'ctn_pago_cheque_titular' => ['sometimes', 'nullable', 'string', 'max:150'],
            'ctn_pago_cheque_mensaje' => ['sometimes', 'nullable', 'string', 'max:300'],
            'ctn_pago_cheque_aviso'   => ['sometimes', 'nullable', 'string', 'max:400'],

            // Cheque: tipos permitidos y modalidades en las que se acepta.
            // Sin 'required': un checkbox desmarcado manda false, no omite la
            // clave, pero otra pantalla puede no mandarla en absoluto.
            // La regla "al menos uno" vive en withValidator().
            'ctn_pago_cheque_vista'    => ['sometimes', 'boolean'],
            'ctn_pago_cheque_diferido' => ['sometimes', 'boolean'],
            'ctn_pago_cheque_retiro'   => ['sometimes', 'boolean'],
            'ctn_pago_cheque_envio'    => ['sometimes', 'boolean'],

            'ctn_retactivo' => ['sometimes', 'boolean'],
            'ctn_retdias'   => ['sometimes', 'required', 'integer', 'min:1', 'max:60'],
            'ctn_retaviso'  => ['sometimes', 'required', 'integer', 'min:0', 'max:30'],
        
            'ctn_pago_cheque_envio'    => ['sometimes', 'boolean'],

            // Momento en que el cheque descuenta stock real.
            'ctn_cheque_stock' => ['sometimes', 'required', 'in:RECEPCION,ACREDITACION'],
        ];
    }

    /**
     * Cada grupo de cheque debe dejar al menos una opción activa.
     *
     * Se evalúa sobre el valor efectivo: el que llega en la petición o, si
     * la clave no vino, el que ya está guardado. Evaluar sólo lo recibido
     * dejaría pasar un PATCH que apaga una opción mientras la otra ya
     * estaba apagada.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->exigirAlMenosUno(
                $v, self::CAMPOS_TIPO,
                'Debe permitir al menos un tipo de cheque.',
            );

            $this->exigirAlMenosUno(
                $v, self::CAMPOS_MODALIDAD,
                'El cheque debe aceptarse en al menos una modalidad de entrega.',
            );
        });
    }

    private function exigirAlMenosUno(Validator $v, array $campos, string $mensaje): void
    {
        // Petición que no toca este grupo (p. ej. la pantalla de stock).
        if (!$this->hasAny($campos)) {
            return;
        }

        // Un valor que ya falló 'boolean' no se puede interpretar.
        if ($v->errors()->hasAny($campos)) {
            return;
        }

        $config = ConfiguracionTienda::actual();

        $hayAlguno = collect($campos)->contains(
            fn (string $campo) => $this->has($campo) ? $this->boolean($campo) : (bool) $config->{$campo},
        );

        if (!$hayAlguno) {
            $v->errors()->add($campos[0], $mensaje);
        }
    }

    public function messages(): array
    {
        return [
            'ctn_pocstk_activo.required' => 'Indique si el aviso de poco stock está activo.',
            'ctn_pocstk_activo.boolean'  => 'El aviso de poco stock debe ser verdadero o falso.',
            'ctn_pocstk_umbral.required' => 'La cantidad para el aviso de poco stock es obligatoria.',
            'ctn_pocstk_umbral.integer'  => 'La cantidad para el aviso de poco stock debe ser un número entero.',
            'ctn_pocstk_umbral.min'      => 'La cantidad para el aviso de poco stock debe ser al menos 1.',
            'ctn_pocstk_umbral.max'      => 'La cantidad para el aviso de poco stock no puede superar 1000.',

            'ctn_pago_cheque_titular.max' => 'El titular receptor no puede superar los 150 caracteres.',
            'ctn_pago_cheque_mensaje.max' => 'El mensaje del proceso de verificación no puede superar los 300 caracteres.',
            'ctn_pago_cheque_aviso.max'   => 'El aviso del plazo no puede superar los 400 caracteres.',

            'ctn_pago_cheque_vista.boolean'    => 'La opción "Cheque a la vista" debe ser verdadera o falsa.',
            'ctn_pago_cheque_diferido.boolean' => 'La opción "Cheque diferido" debe ser verdadera o falsa.',
            'ctn_pago_cheque_retiro.boolean'   => 'La opción de retiro en sucursal debe ser verdadera o falsa.',
            'ctn_pago_cheque_envio.boolean'    => 'La opción de envío a domicilio debe ser verdadera o falsa.',

            'ctn_retdias.required' => 'El plazo de retiro en sucursal es obligatorio.',
            'ctn_retdias.integer'  => 'El plazo de retiro debe ser un número entero de días.',
            'ctn_retdias.min'      => 'El plazo de retiro debe ser de al menos 1 día hábil.',
            'ctn_retdias.max'      => 'El plazo de retiro no puede superar los 60 días hábiles.',
            'ctn_retaviso.integer' => 'El aviso previo debe ser un número entero de días.',
            'ctn_retaviso.min'     => 'El aviso previo no puede ser negativo.',
            'ctn_retaviso.max'     => 'El aviso previo no puede superar los 30 días.',

            'ctn_cheque_stock.in' => 'El momento de descuento de stock del cheque no es válido.',
        
        ];
    }
}