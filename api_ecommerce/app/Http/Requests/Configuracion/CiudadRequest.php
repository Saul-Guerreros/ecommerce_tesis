<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CiudadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('ciudad');

        return [
            'ciu_depid' => ['required', 'integer', 'exists:departamento,dep_id'],
            'ciu_descri' => [
                'required',
                'string',
                'max:50',
                // UNIQUE compuesto (ciu_depid, ciu_descri): el nombre solo debe
                // ser unico dentro del mismo departamento. Existen ciudades
                // homonimas en departamentos distintos.
                Rule::unique('ciudad', 'ciu_descri')
                    ->where(fn ($q) => $q->where('ciu_depid', $this->input('ciu_depid')))
                    ->ignore($id, 'ciu_id'),
            ],
            'ciu_estado' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'ciu_depid.required'  => 'Debe seleccionar el departamento.',
            'ciu_depid.exists'    => 'El departamento seleccionado no existe.',
            'ciu_descri.required' => 'El nombre de la ciudad es obligatorio.',
            'ciu_descri.unique'   => 'Ese departamento ya tiene una ciudad con ese nombre.',
            'ciu_descri.max'      => 'El nombre no puede superar los 50 caracteres.',
        ];
    }
}
