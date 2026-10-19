<?php

namespace App\Http\Requests\Servicio;

use App\Services\Servicio\TecnicoService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A2 - Confirmar recepción. multipart/form-data:
 *   accesorios[]  texto de cada accesorio recibido
 *   fotos[]       fotos de ingreso (OBLIGATORIAS: respaldo legal de la tenencia)
 */
class ConfirmarRecepcionRequest extends FormRequest
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
            'rse_tecid' => [
                'required', 'integer',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (!app(TecnicoService::class)->esTecnicoActivo((int) $value)) {
                        $fail('El usuario seleccionado no es un técnico activo.');
                    }
                },
            ],
            'rse_estfis'   => ['required', 'string', 'min:5', 'max:500'],
            'rse_clave'    => ['nullable', 'string', 'max:100'],
            'rse_obs'      => ['nullable', 'string', 'max:500'],
            'accesorios'   => ['nullable', 'array', 'max:20'],
            'accesorios.*' => ['string', 'max:100', 'distinct:ignore_case'],
            'fotos'        => ['required', 'array', 'min:1', "max:{$max}"],
            'fotos.*'      => ['image', 'mimes:jpg,jpeg,png,webp', "max:{$kb}"],
        ];
    }

    public function messages(): array
    {
        return [
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
        ];
    }
}
