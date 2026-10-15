<?php

namespace App\Http\Requests\Configuracion;

use App\Models\TipoDocumento;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // El parámetro se llama {id} (ver ->parameters() en routes/api.php)
        $id = $this->route('id');

        return [
            'tpd_codigo' => [
                'required', 'string', 'max:10', 'regex:/^[A-Z0-9._-]+$/',
                $this->unico('tpd_codigo', 'Ya existe un tipo de documento con ese código.', $id),
            ],
            'tpd_descri' => [
                'required', 'string', 'max:50',
                $this->unico('tpd_descri', 'Ya existe un tipo de documento con esa descripción.', $id),
            ],
            'tpd_estado' => ['nullable', 'integer', Rule::in([0, 1])],
        ];
    }

    /** Unicidad sin distinguir mayúsculas (los índices únicos de la tabla sí las distinguen). */
    private function unico(string $columna, string $mensaje, mixed $id): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($columna, $mensaje, $id) {
            $existe = TipoDocumento::whereRaw("LOWER({$columna}) = ?", [mb_strtolower(trim((string) $value))])
                ->when($id, fn ($q) => $q->where('tpd_id', '!=', $id))
                ->exists();

            if ($existe) {
                $fail($mensaje);
            }
        };
    }

    /** El código se guarda siempre en mayúsculas y sin espacios. */
    protected function prepareForValidation(): void
    {
        if ($this->has('tpd_codigo')) {
            $this->merge(['tpd_codigo' => mb_strtoupper(trim((string) $this->input('tpd_codigo')))]);
        }
        if ($this->has('tpd_descri')) {
            $this->merge(['tpd_descri' => trim((string) $this->input('tpd_descri'))]);
        }
    }

    public function messages(): array
    {
        return [
            'tpd_codigo.required' => 'El código es obligatorio.',
            'tpd_codigo.max'      => 'El código no puede superar los 10 caracteres.',
            'tpd_codigo.regex'    => 'El código solo admite letras, números, punto, guion y guion bajo (sin espacios).',
            'tpd_descri.required' => 'La descripción es obligatoria.',
            'tpd_descri.max'      => 'La descripción no puede superar los 50 caracteres.',
            'tpd_estado.in'       => 'El estado debe ser Activo (1) o Inactivo (0).',
        ];
    }
}
