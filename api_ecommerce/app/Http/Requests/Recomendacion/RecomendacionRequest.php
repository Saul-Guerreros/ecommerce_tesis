<?php

namespace App\Http\Requests\Recomendacion;

use Illuminate\Foundation\Http\FormRequest;

/** Parámetros de consulta de los endpoints de recomendación (público). */
class RecomendacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // endpoint público; el throttle va en la ruta
    }

    public function rules(): array
    {
        return [
            'limite' => ['sometimes', 'integer', 'min:1', 'max:24'],
        ];
    }

    public function messages(): array
    {
        return [
            'limite.integer' => 'El límite debe ser un número entero.',
            'limite.min'     => 'El límite debe ser al menos :min.',
            'limite.max'     => 'El límite no puede superar :max productos.',
        ];
    }

    public function limite(): int
    {
        return (int) $this->validated('limite', 8);
    }
}