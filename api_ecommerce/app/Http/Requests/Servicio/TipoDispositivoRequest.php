<?php

namespace App\Http\Requests\Servicio;

use App\Models\Servicio\TipoDispositivo;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoDispositivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'tdi_codigo' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/',
                $this->unico('tdi_codigo', 'Ya existe un tipo de dispositivo con ese código.', $id),
            ],
            'tdi_descri' => [
                'required', 'string', 'max:50',
                $this->unico('tdi_descri', 'Ya existe un tipo de dispositivo con esa descripción.', $id),
            ],
            'tdi_estado' => ['nullable', 'integer', Rule::in([0, 1])],
        ];
    }

    /** Unicidad sin distinguir mayúsculas (el índice único de PostgreSQL sí las distingue). */
    private function unico(string $columna, string $mensaje, mixed $id): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($columna, $mensaje, $id) {
            $existe = TipoDispositivo::whereRaw("LOWER({$columna}) = ?", [mb_strtolower(trim((string) $value))])
                ->when($id, fn ($q) => $q->where('tdi_id', '!=', $id))
                ->exists();

            if ($existe) $fail($mensaje);
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tdi_codigo')) {
            $this->merge(['tdi_codigo' => mb_strtoupper(trim((string) $this->input('tdi_codigo')))]);
        }
        if ($this->has('tdi_descri')) {
            $this->merge(['tdi_descri' => trim((string) $this->input('tdi_descri'))]);
        }
    }

    public function messages(): array
    {
        return [
            'tdi_codigo.required' => 'El código es obligatorio.',
            'tdi_codigo.max'      => 'El código no puede superar los 20 caracteres.',
            'tdi_codigo.regex'    => 'El código solo admite letras mayúsculas, números y guion bajo (sin espacios).',
            'tdi_descri.required' => 'La descripción es obligatoria.',
            'tdi_descri.max'      => 'La descripción no puede superar los 50 caracteres.',
            'tdi_estado.in'       => 'El estado debe ser Activo (1) o Inactivo (0).',
        ];
    }
}
