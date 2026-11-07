<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cancelación. "devolucion" es obligatoria si el equipo está en el taller:
 * lo decide el service (depende del estado de ingreso).
 */
class CancelarSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tercero = fn () => $this->input('devolucion.receptor') === 'tercero';

        return [
            'motivo'                  => ['required', 'string', 'min:5', 'max:255'],
            'devolucion'              => ['nullable', 'array'],
            'devolucion.modalidad'    => ['required_with:devolucion', Rule::in(['retiro', 'envio'])],
            'devolucion.ese_sucid'    => ['nullable', 'required_if:devolucion.modalidad,retiro', 'integer', Rule::exists('sucursal', 'suc_id')],
            'devolucion.ese_dirid'    => ['nullable', 'required_if:devolucion.modalidad,envio', 'integer'],
            'devolucion.receptor'     => ['required_with:devolucion', Rule::in(['titular', 'tercero'])],
            'devolucion.ese_nrodoc'   => ['nullable', Rule::requiredIf($tercero), 'regex:/^[0-9]{5,15}$/'],
            'devolucion.ese_nombre'   => ['nullable', Rule::requiredIf($tercero), 'string', 'max:100'],
            'devolucion.ese_apelli'   => ['nullable', Rule::requiredIf($tercero), 'string', 'max:100'],
            'devolucion.ese_telef'    => ['nullable', 'string', 'max:45'],
            'devolucion.accesorios'   => ['nullable', 'array'],
            'devolucion.accesorios.*' => ['integer'],
            'devolucion.ese_obs'      => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required'                  => 'Indicá por qué se cancela la solicitud.',
            'motivo.min'                       => 'El motivo debe tener al menos 5 caracteres.',
            'devolucion.ese_sucid.required_if' => 'Seleccioná la sucursal de retiro.',
            'devolucion.ese_dirid.required_if' => 'Seleccioná la dirección de envío.',
            'devolucion.ese_nrodoc.required'   => 'Ingresá la C.I. de quien retira.',
            'devolucion.ese_nrodoc.regex'      => 'La C.I. debe tener solo números (5 a 15 dígitos).',
            'devolucion.ese_nombre.required'   => 'Ingresá el nombre de quien retira.',
            'devolucion.ese_apelli.required'   => 'Ingresá el apellido de quien retira.',
        ];
    }
}