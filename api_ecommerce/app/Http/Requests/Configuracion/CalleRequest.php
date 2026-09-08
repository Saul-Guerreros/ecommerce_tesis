<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Ya no hay cal_ciuid: al no existir la redundancia calle/barrio-ciudad,
     * desaparece tambien el withValidator() de consistencia que tenia la
     * version anterior de este Request. La ciudad queda garantizada por una
     * unica fuente de verdad: barrio.bar_ciuid.
     */
    public function rules(): array
    {
        $id = $this->route('calle');

        return [
            'cal_barid' => ['required', 'integer', 'exists:barrio,bar_id'],
            'cal_descri' => [
                'required',
                'string',
                'max:80',
                // La misma calle puede repetirse en barrios distintos
                // (ej. "Avda. Mcal. Lopez" cruza varios barrios); no puede
                // repetirse dentro del mismo barrio.
                Rule::unique('calle', 'cal_descri')
                    ->where(fn ($q) => $q->where('cal_barid', $this->input('cal_barid')))
                    ->ignore($id, 'cal_id'),
            ],
            'cal_estado' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'cal_barid.required'  => 'Debe seleccionar el barrio.',
            'cal_barid.exists'    => 'El barrio seleccionado no existe.',
            'cal_descri.required' => 'El nombre de la calle es obligatorio.',
            'cal_descri.unique'   => 'Ese barrio ya tiene una calle con ese nombre.',
            'cal_descri.max'      => 'El nombre no puede superar los 80 caracteres.',
        ];
    }
}
