<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class ConfiguracionTiendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Los tres campos son obligatorios: es la configuración general y no
     * puede quedar a medias. El umbral se exige aunque el aviso esté
     * desactivado, para que al volver a activarlo ya tenga un valor válido.
     */
    public function rules(): array
    {
        return [
            'ctn_pocstk_activo' => ['required', 'boolean'],
            'ctn_pocstk_umbral' => ['required', 'integer', 'min:1', 'max:1000'],
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
            'ctn_maxstk_activo.required' => 'Indique si el aviso de máximo disponible está activo.',
            'ctn_maxstk_activo.boolean'  => 'El aviso de máximo disponible debe ser verdadero o falso.',
        ];
    }
}