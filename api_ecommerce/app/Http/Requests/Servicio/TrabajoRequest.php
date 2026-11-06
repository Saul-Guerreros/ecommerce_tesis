<?php

namespace App\Http\Requests\Servicio;

use App\Models\Servicio\Trabajo;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrabajoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'trb_codigo' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/',
                $this->unico('trb_codigo', 'Ya existe un trabajo con ese código.', $id),
            ],
            'trb_descri' => [
                'required', 'string', 'max:100',
                $this->unico('trb_descri', 'Ya existe un trabajo con esa descripción.', $id),
            ],
            'trb_precio' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'trb_tivid'  => ['required', 'integer', Rule::exists('tipo_iva', 'tiv_id')->where('tiv_estado', 1)],
            'trb_dias'   => ['sometimes', 'integer', 'min:1', 'max:60'],
            'trb_estado' => ['nullable', 'integer', Rule::in([0, 1])],
        ];
    }

    private function unico(string $columna, string $mensaje, mixed $id): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($columna, $mensaje, $id) {
            $existe = Trabajo::whereRaw("LOWER({$columna}) = ?", [mb_strtolower(trim((string) $value))])
                ->when($id, fn ($q) => $q->where('trb_id', '!=', $id))
                ->exists();

            if ($existe) $fail($mensaje);
        };
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'trb_codigo' => mb_strtoupper(trim((string) $this->input('trb_codigo'))),
            'trb_descri' => trim((string) $this->input('trb_descri')),
        ]);
    }

    public function messages(): array
    {
        return [
            'trb_codigo.required' => 'El código es obligatorio.',
            'trb_codigo.max'      => 'El código no puede superar los 20 caracteres.',
            'trb_codigo.regex'    => 'El código solo admite letras mayúsculas, números y guion bajo (sin espacios).',
            'trb_descri.required' => 'La descripción es obligatoria.',
            'trb_descri.max'      => 'La descripción no puede superar los 100 caracteres.',
            'trb_precio.required' => 'El precio es obligatorio.',
            'trb_precio.numeric'  => 'El precio debe ser un número.',
            'trb_precio.min'      => 'El precio no puede ser negativo.',
            'trb_tivid.required'  => 'El tipo de IVA es obligatorio.',
            'trb_tivid.exists'    => 'El tipo de IVA seleccionado no existe o está inactivo.',
            'trb_estado.in'       => 'El estado debe ser Activo (1) o Inactivo (0).',
        ];
    }
}
