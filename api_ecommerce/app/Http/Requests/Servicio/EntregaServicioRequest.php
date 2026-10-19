<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A5 - Registrar entrega. accesorios[] = rac_id de los accesorios devueltos. */
class EntregaServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tercero = fn () => $this->input('receptor') === 'tercero';

        return [
            'modalidad'    => ['required', Rule::in(['retiro', 'envio'])],
            'ese_sucid'    => ['nullable', 'required_if:modalidad,retiro', 'integer', Rule::exists('sucursal', 'suc_id')],
            'ese_dirid'    => ['nullable', 'required_if:modalidad,envio', 'integer'],
            'receptor'     => ['required', Rule::in(['titular', 'tercero'])],
            'ese_nrodoc'   => ['nullable', Rule::requiredIf($tercero), 'regex:/^[0-9]{5,15}$/'],
            'ese_nombre'   => ['nullable', Rule::requiredIf($tercero), 'string', 'max:100'],
            'ese_apelli'   => ['nullable', Rule::requiredIf($tercero), 'string', 'max:100'],
            'ese_telef'    => ['nullable', 'string', 'max:45'],
            'accesorios'   => ['nullable', 'array'],
            'accesorios.*' => ['integer'],
            'ese_obs'      => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'modalidad.required'    => 'Indique cómo se entrega el equipo.',
            'ese_sucid.required_if' => 'Seleccione la sucursal de retiro.',
            'ese_dirid.required_if' => 'Seleccione la dirección de envío.',
            'ese_nrodoc.required'   => 'Ingrese la C.I. de quien retira.',
            'ese_nrodoc.regex'      => 'La C.I. debe tener solo números (5 a 15 dígitos).',
            'ese_nombre.required'   => 'Ingrese el nombre de quien retira.',
            'ese_apelli.required'   => 'Ingrese el apellido de quien retira.',
            'ese_obs.max'           => 'La observación no puede superar los 255 caracteres.',
        ];
    }
}
