<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BarrioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('barrio');

        return [
            'bar_ciuid' => ['required', 'integer', 'exists:ciudad,ciu_id'],
            'bar_descri' => [
                'required',
                'string',
                'max:50',
                // UNIQUE compuesto (bar_ciuid, bar_descri): "Centro" o
                // "San Miguel" se repiten entre ciudades; el nombre solo debe
                // ser unico dentro de la misma ciudad.
                Rule::unique('barrio', 'bar_descri')
                    ->where(fn ($q) => $q->where('bar_ciuid', $this->input('bar_ciuid')))
                    ->ignore($id, 'bar_id'),
            ],
            'bar_estado' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'bar_ciuid.required'  => 'Debe seleccionar la ciudad.',
            'bar_ciuid.exists'    => 'La ciudad seleccionada no existe.',
            'bar_descri.required' => 'El nombre del barrio es obligatorio.',
            'bar_descri.unique'   => 'Esa ciudad ya tiene un barrio con ese nombre.',
            'bar_descri.max'      => 'El nombre no puede superar los 50 caracteres.',
        ];
    }
}
