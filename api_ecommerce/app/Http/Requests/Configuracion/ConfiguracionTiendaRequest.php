<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class ConfiguracionTiendaRequest extends FormRequest
{
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
            // Interruptor general de la funcionalidad. No es 'required':
            // un checkbox desmarcado no manda la clave, así que exigirla
            // rompería el guardado apenas el admin lo apague.
            'ctn_retactivo' => ['sometimes', 'boolean'],

            // Plazo de retiro en sucursal, en días hábiles. min:1 porque
            // un 0 dejaría al cliente sin plazo real para ir a buscar.
            'ctn_retdias'  => ['sometimes', 'required', 'integer', 'min:1', 'max:60'],
            // Aviso previo, en días antes del vencimiento. 0 = no avisar,
            // que es lo que permite dejarlo apagado mientras las
            // notificaciones no estén implementadas.
            'ctn_retaviso' => ['sometimes', 'required', 'integer', 'min:0', 'max:30'],
        ];
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
            'ctn_retdias.required'       => 'El plazo de retiro en sucursal es obligatorio.',
            'ctn_retdias.integer'        => 'El plazo de retiro debe ser un número entero de días.',
            'ctn_retdias.min'            => 'El plazo de retiro debe ser de al menos 1 día hábil.',
            'ctn_retdias.max'            => 'El plazo de retiro no puede superar los 60 días hábiles.',
            'ctn_retaviso.integer'       => 'El aviso previo debe ser un número entero de días.',
            'ctn_retaviso.min'           => 'El aviso previo no puede ser negativo.',
            'ctn_retaviso.max'           => 'El aviso previo no puede superar los 30 días.',
        ];
    }
}