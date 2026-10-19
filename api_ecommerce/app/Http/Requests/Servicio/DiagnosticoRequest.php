<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * T2 - Diagnóstico. multipart/form-data:
 *   fallas[i][fll_id], fallas[i][obs], fotos[]
 */
class DiagnosticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $repara = filter_var($this->input('dia_repara'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $this->merge([
            'dia_repara' => $repara,
            'dia_reqrep' => filter_var($this->input('dia_reqrep', false), FILTER_VALIDATE_BOOLEAN),
            'dia_causa'  => trim((string) $this->input('dia_causa')),
            // No reparable => sin tiempo estimado (lo exige el CHECK de la tabla).
            'dia_tiempo' => $repara ? $this->input('dia_tiempo') : null,
        ]);
    }

    public function rules(): array
    {
        $kb  = (int) config('servicio.foto_max_kb');
        $max = (int) config('servicio.max_fotos_diagnostico');

        return [
            'dia_causa'  => ['required', 'string', 'min:5', 'max:500'],
            'dia_repara' => ['required', 'boolean'],
            'dia_reqrep' => ['boolean'],
            'dia_tiempo' => ['nullable', 'required_if:dia_repara,true', 'integer', 'min:1', 'max:365'],

            'fallas'          => ['required', 'array', 'min:1', 'max:15'],
            'fallas.*.fll_id' => ['required', 'integer', 'distinct', Rule::exists('falla', 'fll_id')->where('fll_estado', 1)],
            'fallas.*.obs'    => ['nullable', 'string', 'max:255'],

            'fotos'   => ['nullable', 'array', "max:{$max}"],
            'fotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', "max:{$kb}"],
        ];
    }

    public function messages(): array
    {
        return [
            'dia_causa.required'       => 'Indique la causa encontrada.',
            'dia_causa.min'            => 'La causa debe tener al menos 5 caracteres.',
            'dia_causa.max'            => 'La causa no puede superar los 500 caracteres.',
            'dia_repara.required'      => 'Indique si el equipo es reparable.',
            'dia_tiempo.required_if'   => 'Indique los días estimados de reparación.',
            'dia_tiempo.min'           => 'El tiempo estimado debe ser de al menos 1 día.',
            'dia_tiempo.max'           => 'El tiempo estimado no puede superar 365 días.',
            'fallas.required'          => 'Registre al menos una falla.',
            'fallas.min'               => 'Registre al menos una falla.',
            'fallas.max'               => 'Máximo 15 fallas por diagnóstico.',
            'fallas.*.fll_id.required' => 'Seleccione la falla.',
            'fallas.*.fll_id.distinct' => 'Hay fallas repetidas.',
            'fallas.*.fll_id.exists'   => 'La falla seleccionada no existe o está inactiva.',
            'fallas.*.obs.max'         => 'La observación de la falla admite hasta 255 caracteres.',
            'fotos.max'                => 'Puede adjuntar como máximo :max fotos.',
            'fotos.*.image'            => 'Solo se admiten imágenes.',
            'fotos.*.mimes'            => 'Las fotos deben ser JPG, PNG o WEBP.',
            'fotos.*.max'              => 'Cada foto puede pesar como máximo 5 MB.',
        ];
    }
}
