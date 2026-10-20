<?php

namespace App\Http\Requests\Credito;

use App\Models\Credito\SolicitudCredito;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Paso 1 del wizard, con sus tres sub-pasos en una sola peticion:
 * identificacion (1/3), domicilio (2/3) y situacion personal (3/3).
 *
 * Los nombres de los campos son los de la columna destino (scr_*) y no
 * alias amigables. El borrador guarda este arreglo tal cual en
 * solicitud_temporal.stm_person, y al generar la solicitud el servicio
 * lo vuelca sobre solicitud_credito sin tabla de traduccion en el
 * medio: un campo mal escrito falla en la validacion y no tres capas
 * mas abajo.
 */
class SolicitudPersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ---------- 1/3 Identificacion ----------
            'scr_cedula' => ['required', 'string', 'max:50', 'regex:/^[0-9]{5,15}$/'],
            'scr_nombre' => ['required', 'string', 'max:95'],
            'scr_apelli' => ['required', 'string', 'max:100'],

            // Mayoria de edad: no se puede otorgar credito a un menor.
            // El CHECK de la migracion solo exige que la fecha sea pasada;
            // los 18 anios son regla de negocio y viven aca.
            'scr_fechna' => ['required', 'date', 'before:' . now()->subYears(18)->toDateString()],

            'scr_celula' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{6,20}$/'],
            'scr_email'  => ['required', 'email', 'max:100'],

            // ---------- 2/3 Domicilio ----------
            // El departamento no se envia: se deduce de ciudad.ciu_depid.
            'scr_ciuid' => [
                'required', 'integer',
                Rule::exists('ciudad', 'ciu_id')->where('ciu_estado', 1),
            ],
            'scr_barrio'  => ['required', 'string', 'max:60'],
            'scr_calle'   => ['required', 'string', 'max:100'],
            'scr_nrocasa' => ['nullable', 'string', 'max:45'],
            'scr_referen' => ['nullable', 'string', 'max:250'],

            // ---------- 3/3 Situacion personal ----------
            'scr_estciv' => ['required', Rule::in(SolicitudCredito::ESTADOS_CIVILES)],

            // Relacion laboral y profesion son INDEPENDIENTES: no se
            // valida ninguna combinacion entre ambas, porque todas son
            // validas (un Medico puede ser funcionario publico, privado
            // o independiente).
            'scr_tprid' => [
                'required', 'integer',
                Rule::exists('tipo_profesion', 'tpr_id')->where('tpr_estado', 1),
            ],
            'scr_proid'  => [
                'required', 'integer',
                Rule::exists('profesion', 'pro_id')->where('pro_estado', 1),
            ],
            'scr_tipviv' => ['required', Rule::in(SolicitudCredito::TIPOS_VIVIENDA)],

            // Sin asterisco en el instructivo: se piden, pero no se exigen.
            'scr_sertit' => ['nullable', 'string', 'max:150'],
            'scr_relsti' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $esPropia = $this->input('scr_tipviv') === SolicitudCredito::VIVIENDA_PROPIA;

            // Espejo del CHECK ck_scr_vivienda. Sin esto, un POST armado a
            // mano llegaria hasta el INSERT y reventaria con un error de
            // Postgres en vez de un 422 legible.
            if (!$esPropia && ($this->filled('scr_sertit') || $this->filled('scr_relsti'))) {
                $v->errors()->add(
                    'scr_sertit',
                    'Los datos del servicio ANDE/ESSAP solo se cargan cuando la vivienda es propia.'
                );
            }

            // Si declara a nombre de quien esta el servicio, tiene que
            // decir que relacion tiene con esa persona: ese par es lo que
            // le sirve al administrador para verificar el domicilio.
            if ($esPropia && $this->filled('scr_sertit') && !$this->filled('scr_relsti')) {
                $v->errors()->add(
                    'scr_relsti',
                    'Indicá qué relación tenés con el titular del servicio.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'scr_cedula.required' => 'El número de cédula es obligatorio.',
            'scr_cedula.regex'    => 'La cédula debe tener entre 5 y 15 dígitos, sin puntos.',
            'scr_nombre.required' => 'Ingresá tus nombres.',
            'scr_apelli.required' => 'Ingresá tus apellidos.',
            'scr_fechna.required' => 'La fecha de nacimiento es obligatoria.',
            'scr_fechna.before'   => 'Para solicitar un crédito tenés que ser mayor de 18 años.',
            'scr_celula.required' => 'El número de celular es obligatorio.',
            'scr_celula.regex'    => 'El celular solo admite números, espacios y guiones.',
            'scr_email.required'  => 'El correo electrónico es obligatorio.',
            'scr_email.email'     => 'Ingresá un correo electrónico válido.',
            'scr_ciuid.required'  => 'Seleccioná tu ciudad.',
            'scr_ciuid.exists'    => 'La ciudad seleccionada no está disponible.',
            'scr_barrio.required' => 'El barrio es obligatorio.',
            'scr_calle.required'  => 'La calle es obligatoria.',
            'scr_estciv.required' => 'Seleccioná tu estado civil.',
            'scr_estciv.in'       => 'El estado civil seleccionado no es válido.',
            'scr_tprid.required'  => 'Seleccioná tu tipo de profesión.',
            'scr_tprid.exists'    => 'El tipo de profesión seleccionado no está disponible.',
            'scr_proid.required'  => 'Seleccioná tu profesión.',
            'scr_proid.exists'    => 'La profesión seleccionada no está disponible.',
            'scr_tipviv.required' => 'Seleccioná el tipo de vivienda.',
            'scr_tipviv.in'       => 'El tipo de vivienda seleccionado no es válido.',
        ];
    }
}