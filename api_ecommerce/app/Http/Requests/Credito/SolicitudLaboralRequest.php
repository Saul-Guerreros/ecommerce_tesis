<?php

namespace App\Http\Requests\Credito;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paso 2 del wizard: lugar de trabajo (1/2) y situacion economica
 * (2/2), en una sola peticion.
 *
 * La antiguedad que el formulario muestra bajo "Fecha de ingreso" NO se
 * envia ni se valida: es un calculo derivado de scr_fecing que Angular
 * pinta para confirmarle al cliente que entendio bien la fecha.
 */
class SolicitudLaboralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scr_empres' => ['required', 'string', 'max:150'],
            'scr_dirlab' => ['required', 'string', 'max:200'],
            'scr_tellab' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{6,20}$/'],
            'scr_cargo'  => ['required', 'string', 'max:100'],

            // min:1 y no min:0: un ingreso declarado en cero no es un dato
            // valido para evaluar un credito, es un campo sin completar.
            'scr_ingres' => ['required', 'numeric', 'min:1', 'max:99999999999.99'],

            'scr_fecing' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'scr_empres.required'      => 'Ingresá la empresa o lugar de trabajo.',
            'scr_dirlab.required'      => 'La dirección laboral es obligatoria.',
            'scr_tellab.required'      => 'El teléfono laboral es obligatorio.',
            'scr_tellab.regex'         => 'El teléfono solo admite números, espacios y guiones.',
            'scr_cargo.required'       => 'Ingresá tu cargo o puesto.',
            'scr_ingres.required'      => 'El ingreso mensual es obligatorio.',
            'scr_ingres.numeric'       => 'El ingreso mensual debe ser un monto numérico.',
            'scr_ingres.min'           => 'El ingreso mensual declarado debe ser mayor a cero.',
            'scr_fecing.required'      => 'La fecha de ingreso es obligatoria.',
            'scr_fecing.before_or_equal' => 'La fecha de ingreso no puede ser futura.',
        ];
    }
}