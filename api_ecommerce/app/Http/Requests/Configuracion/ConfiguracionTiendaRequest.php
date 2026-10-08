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
        ];
    }
}