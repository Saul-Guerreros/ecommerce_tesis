<?php

namespace App\Http\Requests\Catalogo;

use App\Models\Catalogo\Resena;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ocultar / volver a mostrar una reseña. El motivo es obligatorio solo al
 * OCULTAR (la reseña está visible): el cliente lo ve en su propia reseña.
 */
class CambiarEstadoResenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // middleware permiso:resenas.gestionar
    }

    protected function prepareForValidation(): void
    {
        $motivo = $this->input('res_motocu');

        $this->merge([
            'res_motocu' => is_string($motivo) ? (trim(strip_tags($motivo)) ?: null) : null,
        ]);
    }

    public function rules(): array
    {
        $seVaAOcultar = fn () => Resena::whereKey($this->route('id'))
            ->where('res_estado', Resena::VISIBLE)
            ->exists();

        return [
            'res_motocu' => [Rule::requiredIf($seVaAOcultar), 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'res_motocu.required' => 'Indicá el motivo por el que se oculta la reseña.',
            'res_motocu.max'      => 'El motivo admite hasta 255 caracteres.',
        ];
    }
}