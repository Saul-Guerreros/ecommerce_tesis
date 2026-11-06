<?php

namespace App\Http\Requests\Servicio;

/** Envío del producto a la empresa: los mismos datos del reclamo más la fecha de envío. */
class GarantiaEnviarRequest extends GarantiaDatosRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'gpr_fecenv' => ['required', 'date', 'before_or_equal:today'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'gpr_fecenv.required'        => 'Indique la fecha de envío a la empresa.',
            'gpr_fecenv.before_or_equal' => 'La fecha de envío no puede ser futura.',
        ]);
    }
}
