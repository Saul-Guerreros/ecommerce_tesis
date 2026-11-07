<?php

namespace App\Http\Requests\Configuracion;

use App\Rules\UrlRedSocial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DatosContactoRequest extends FormRequest
{
    private const TEXTOS = [
        'dco_titulo', 'dco_intro', 'dco_direccion', 'dco_referencia', 'dco_telefono',
        'dco_email', 'dco_whatsapp_mensaje', 'dco_horario',
        'dco_facebook', 'dco_instagram', 'dco_youtube', 'dco_tiktok',
    ];

    public function authorize(): bool
    {
        return true; // el permiso lo controla el middleware de la ruta
    }

    protected function prepareForValidation(): void
    {
        $datos = [];

        foreach (self::TEXTOS as $campo) {
            if ($this->has($campo)) {
                $valor = $this->input($campo);
                $valor = is_string($valor) ? trim(strip_tags($valor)) : $valor;
                $datos[$campo] = $valor === '' ? null : $valor;
            }
        }

        // WhatsApp: se guarda solo con dígitos (wa.me no acepta +, espacios ni guiones).
        if ($this->has('dco_whatsapp')) {
            $digitos = preg_replace('/\D/', '', (string) $this->input('dco_whatsapp'));
            $datos['dco_whatsapp'] = $digitos === '' ? null : $digitos;
        }

        $this->merge($datos);
    }

    public function rules(): array
    {
        return [
            'dco_titulo'           => ['required', 'string', 'max:120'],
            'dco_intro'            => ['nullable', 'string', 'max:500'],
            'dco_direccion'        => ['nullable', 'string', 'max:255'],
            'dco_referencia'       => ['nullable', 'string', 'max:255'],
            'dco_telefono'         => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]{6,30}$/'],
            'dco_email'            => ['nullable', 'email:rfc', 'max:120'],
            'dco_whatsapp'         => ['nullable', 'regex:/^[1-9][0-9]{9,14}$/'],
            'dco_whatsapp_mensaje' => ['nullable', 'string', 'max:200'],
            'dco_horario'          => ['nullable', 'string', 'max:500'],

            'dco_mapa_lat'  => ['nullable', 'numeric', 'between:-90,90', 'required_with:dco_mapa_lng'],
            'dco_mapa_lng'  => ['nullable', 'numeric', 'between:-180,180', 'required_with:dco_mapa_lat'],
            'dco_mapa_zoom' => ['required', 'integer', 'between:10,20'],

            'dco_facebook'  => ['nullable', 'string', 'max:255', new UrlRedSocial(['facebook.com', 'fb.com'])],
            'dco_instagram' => ['nullable', 'string', 'max:255', new UrlRedSocial(['instagram.com'])],
            'dco_youtube'   => ['nullable', 'string', 'max:255', new UrlRedSocial(['youtube.com', 'youtu.be'])],
            'dco_tiktok'    => ['nullable', 'string', 'max:255', new UrlRedSocial(['tiktok.com'])],

            'dco_publicado' => ['required', 'boolean'],
        ];
    }

    /** Si la página se publica, debe tener al menos un canal de contacto. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $this->boolean('dco_publicado')) {
                return;
            }
            if (! $this->input('dco_telefono') && ! $this->input('dco_email') && ! $this->input('dco_whatsapp')) {
                $v->errors()->add('dco_telefono', 'Cargá al menos un teléfono, email o WhatsApp para publicar la página.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'dco_titulo.required'        => 'El título es obligatorio.',
            'dco_telefono.regex'         => 'Solo números, espacios, +, ( ) y guiones.',
            'dco_email.email'            => 'Ingresá un email válido.',
            'dco_whatsapp.regex'         => 'Formato internacional sin el +, ej: 595981123456.',
            'dco_mapa_lat.between'       => 'La latitud debe estar entre -90 y 90.',
            'dco_mapa_lng.between'       => 'La longitud debe estar entre -180 y 180.',
            'dco_mapa_lat.required_with' => 'Cargá latitud y longitud juntas.',
            'dco_mapa_lng.required_with' => 'Cargá latitud y longitud juntas.',
            'dco_mapa_zoom.between'      => 'El zoom debe estar entre 10 y 20.',
        ];
    }
}