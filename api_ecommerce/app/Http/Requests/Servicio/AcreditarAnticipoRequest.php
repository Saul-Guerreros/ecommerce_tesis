<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;

/** NC del anticipo. Sin monto = todo lo disponible (lo calcula el backend). */
class AcreditarAnticipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monto'       => ['nullable', 'integer', 'min:1', 'max:999999999999'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'monto.integer'   => 'El monto se expresa en guaraníes, sin decimales.',
            'monto.min'       => 'El monto debe ser mayor que cero.',
            'observacion.max' => 'La observación no puede superar los 255 caracteres.',
        ];
    }
}