<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ingreso presencial (mostrador): solicitud + recepción en un solo envío. multipart/form-data:
 *   accesorios[]  texto de cada accesorio recibido
 *   fotos[]       fotos de INGRESO (obligatorias: respaldo legal de la tenencia)
 *
 * Reglas que dependen de la BD (cliente activo con documento, sucursal del funcionario,
 * técnico de esa sucursal, línea en garantía) se validan en el service con ValidationException.
 */
class RegistrarMostradorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $accesorios = collect((array) $this->input('accesorios', []))
            ->map(fn ($a) => trim((string) $a))
            ->filter()
            ->values()
            ->all();

        $this->merge(['accesorios' => $accesorios]);
    }

    public function rules(): array
    {
        $kb  = (int) config('servicio.foto_max_kb');
        $max = (int) config('servicio.max_fotos_ingreso');

        return [
            // Cliente y sucursal
            'sse_cliid'  => ['required', 'integer'],
            'sse_sucid'  => ['required', 'integer'],

            // Tipo de servicio
            'sse_garant' => ['required', 'boolean'],
            // Paso 3: reclamo de garantía de un servicio anterior (número SS-). El equipo sale de la original.
            'sse_sseori' => ['nullable', 'string', 'max:20'],
            'sse_dpdid'  => ['nullable', Rule::requiredIf(fn () => $this->boolean('sse_garant') && !$this->filled('sse_sseori')), 'integer'],

            // Equipo (mismas reglas que el wizard web; en un reclamo se toman de la original)
            'sse_tdiid'  => ['nullable', Rule::requiredIf(fn () => !$this->filled('sse_sseori')), 'integer',
                             Rule::exists('tipo_dispositivo', 'tdi_id')->where('tdi_estado', 1)],
            'sse_marca'  => ['nullable', Rule::requiredIf(fn () => !$this->filled('sse_sseori')), 'string', 'max:50'],
            'sse_modelo' => ['nullable', Rule::requiredIf(fn () => !$this->filled('sse_sseori')), 'string', 'max:80'],
            'sse_nroser' => ['nullable', 'string', 'max:80'],
            'sse_proble' => ['required', 'string', 'min:10', 'max:500'],
            'sse_acceso' => ['nullable', 'string', 'max:255'],

            // Recepción (mismas reglas que A2)
            'rse_tecid'    => ['required', 'integer'],
            'rse_estfis'   => ['required', 'string', 'min:5', 'max:500'],
            'rse_clave'    => ['nullable', 'string', 'max:100'],
            'rse_obs'      => ['nullable', 'string', 'max:500'],
            'accesorios'   => ['nullable', 'array', 'max:20'],
            'accesorios.*' => ['string', 'max:100', 'distinct:ignore_case'],
            'fotos'        => ['required', 'array', 'min:1', "max:{$max}"],
            'fotos.*'      => ['image', 'mimes:jpg,jpeg,png,webp', "max:{$kb}"],

            // El cliente firma la constancia de recepción con las condiciones del servicio.
            'acepta_condiciones' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'sse_cliid.required'    => 'Seleccione o registre el cliente.',
            'sse_sucid.required'    => 'Seleccione la sucursal de recepción.',
            'sse_dpdid.required'    => 'Seleccione el producto que cubre la garantía.',
            'sse_tdiid.required'    => 'Seleccione el tipo de equipo.',
            'sse_tdiid.exists'      => 'El tipo de equipo no es válido.',
            'sse_marca.required'    => 'Indique la marca del equipo.',
            'sse_modelo.required'   => 'Indique el modelo del equipo.',
            'sse_proble.required'   => 'Describa el problema del equipo.',
            'sse_proble.min'        => 'Describa el problema con al menos 10 caracteres.',
            'sse_proble.max'        => 'La descripción no puede superar los 500 caracteres.',
            'rse_tecid.required'    => 'Asigne el técnico responsable.',
            'rse_estfis.required'   => 'Describa el estado físico del equipo.',
            'rse_estfis.min'        => 'El estado físico debe tener al menos 5 caracteres.',
            'rse_estfis.max'        => 'El estado físico no puede superar los 500 caracteres.',
            'rse_clave.max'         => 'La clave no puede superar los 100 caracteres.',
            'accesorios.max'        => 'Máximo 20 accesorios.',
            'accesorios.*.max'      => 'Cada accesorio admite hasta 100 caracteres.',
            'accesorios.*.distinct' => 'Hay accesorios repetidos.',
            'fotos.required'        => 'Las fotos de ingreso son obligatorias (respaldo de la tenencia del equipo).',
            'fotos.min'             => 'Adjunte al menos una foto del equipo.',
            'fotos.max'             => 'Puede adjuntar como máximo :max fotos.',
            'fotos.*.image'         => 'Solo se admiten imágenes.',
            'fotos.*.mimes'         => 'Las fotos deben ser JPG, PNG o WEBP.',
            'fotos.*.max'           => 'Cada foto puede pesar como máximo 5 MB.',
            'acepta_condiciones.accepted' => 'Confirme que el cliente aceptó las condiciones del servicio.',
        ];
    }
}
