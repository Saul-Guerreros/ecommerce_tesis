<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TimbradoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * tim_empid y tim_nroact NO forman parte de las reglas: no son datos que
     * el usuario cargue desde el formulario.
     * - tim_empid: lo asigna el controlador a partir de la empresa autenticada.
     * - tim_nroact: lo inicializa el controlador en store() (tim_nroini - 1)
     *   y no se toca en update() a traves de este Request.
     */
    public function rules(): array
    {
        $id = $this->route('timbrado');

        return [
            'tim_tcoid'    => ['required', 'integer', 'exists:tipo_comprobante,tco_id'],
            'tim_sucid'    => ['required', 'integer', 'exists:sucursal,suc_id'],
            'tim_numero'   => [
                'required',
                'string',
                'size:8',
                Rule::unique('timbrado', 'tim_numero')->ignore($id, 'tim_id'),
            ],
            'tim_establec' => ['required', 'string', 'size:3', 'regex:/^[0-9]{3}$/'],
            'tim_puntexp'  => ['required', 'string', 'size:3', 'regex:/^[0-9]{3}$/'],
            'tim_fechin'   => ['required', 'date'],
            'tim_fechfn'   => ['required', 'date', 'after:tim_fechin'],
            'tim_nroini'   => ['required', 'integer', 'min:1'],
            'tim_nrofin'   => ['required', 'integer', 'gt:tim_nroini'],
            'tim_observacion' => ['nullable', 'string', 'max:150'],
            'tim_estado'   => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    /**
     * Regla de negocio: dentro de una misma empresa, la misma sucursal no
     * puede tener dos timbrados ACTIVOS del mismo tipo de comprobante con
     * rangos de fecha superpuestos. Se permite historial (timbrados vencidos
     * o desactivados) conviviendo con uno nuevo vigente.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('tim_fechin') || $validator->errors()->has('tim_fechfn')) {
                return;
            }

            $id = $this->route('timbrado');

            $superpuesto = DB::table('timbrado')
                ->where('tim_empid', auth()->user()->emp_id)
                ->where('tim_sucid', $this->input('tim_sucid'))
                ->where('tim_tcoid', $this->input('tim_tcoid'))
                ->where('tim_estado', 1)
                ->when($id, fn ($q) => $q->where('tim_id', '!=', $id))
                ->where('tim_fechin', '<=', $this->input('tim_fechfn'))
                ->where('tim_fechfn', '>=', $this->input('tim_fechin'))
                ->exists();

            if ($superpuesto) {
                $validator->errors()->add(
                    'tim_numero',
                    'Ya existe un timbrado activo de este tipo de comprobante para esta sucursal con fechas que se superponen.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'tim_tcoid.required'    => 'Debe seleccionar el tipo de comprobante.',
            'tim_tcoid.exists'      => 'El tipo de comprobante seleccionado no existe.',
            'tim_sucid.required'    => 'Debe seleccionar la sucursal.',
            'tim_sucid.exists'      => 'La sucursal seleccionada no existe.',
            'tim_numero.required'   => 'El número de timbrado es obligatorio.',
            'tim_numero.size'       => 'El número de timbrado debe tener exactamente 8 caracteres.',
            'tim_numero.unique'     => 'Ese número de timbrado ya está registrado.',
            'tim_establec.required' => 'El establecimiento es obligatorio.',
            'tim_establec.size'     => 'El establecimiento debe tener exactamente 3 dígitos.',
            'tim_establec.regex'    => 'El establecimiento debe ser numérico (ej: 001).',
            'tim_puntexp.required'  => 'El punto de expedición es obligatorio.',
            'tim_puntexp.size'      => 'El punto de expedición debe tener exactamente 3 dígitos.',
            'tim_puntexp.regex'     => 'El punto de expedición debe ser numérico (ej: 001).',
            'tim_fechin.required'   => 'La fecha de inicio de vigencia es obligatoria.',
            'tim_fechfn.required'   => 'La fecha de fin de vigencia es obligatoria.',
            'tim_fechfn.after'      => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'tim_nroini.required'   => 'El número inicial es obligatorio.',
            'tim_nrofin.required'   => 'El número final es obligatorio.',
            'tim_nrofin.gt'         => 'El número final debe ser mayor al número inicial.',
        ];
    }
}