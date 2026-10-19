<?php

namespace App\Http\Requests\Servicio;

use App\Models\Servicio\Falla;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FallaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'fll_codigo' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/',
                function (string $attribute, mixed $value, Closure $fail) use ($id) {
                    $existe = Falla::whereRaw('LOWER(fll_codigo) = ?', [mb_strtolower($value)])
                        ->when($id, fn ($q) => $q->where('fll_id', '!=', $id))
                        ->exists();
                    if ($existe) $fail('Ya existe una falla con ese código.');
                },
            ],
            'fll_descri' => ['required', 'string', 'max:100'],
            // null = falla genérica (aplica a cualquier tipo de equipo)
            'fll_tdiid'  => ['nullable', 'integer', Rule::exists('tipo_dispositivo', 'tdi_id')],
            'fll_estado' => ['nullable', 'integer', Rule::in([0, 1])],
        ];
    }

    /** La misma descripción no puede repetirse dentro del mismo tipo (o entre las genéricas). */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->hasAny(['fll_descri', 'fll_tdiid'])) return;

            $id   = $this->route('id');
            $tdi  = $this->input('fll_tdiid');

            $existe = Falla::whereRaw('LOWER(fll_descri) = ?', [mb_strtolower($this->input('fll_descri'))])
                ->when($tdi, fn ($q) => $q->where('fll_tdiid', $tdi), fn ($q) => $q->whereNull('fll_tdiid'))
                ->when($id, fn ($q) => $q->where('fll_id', '!=', $id))
                ->exists();

            if ($existe) {
                $v->errors()->add('fll_descri', 'Ya existe una falla con esa descripción para el mismo tipo de dispositivo.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fll_codigo' => mb_strtoupper(trim((string) $this->input('fll_codigo'))),
            'fll_descri' => trim((string) $this->input('fll_descri')),
            'fll_tdiid'  => $this->input('fll_tdiid') ?: null,
        ]);
    }

    public function messages(): array
    {
        return [
            'fll_codigo.required' => 'El código es obligatorio.',
            'fll_codigo.max'      => 'El código no puede superar los 20 caracteres.',
            'fll_codigo.regex'    => 'El código solo admite letras mayúsculas, números y guion bajo (sin espacios).',
            'fll_descri.required' => 'La descripción es obligatoria.',
            'fll_descri.max'      => 'La descripción no puede superar los 100 caracteres.',
            'fll_tdiid.exists'    => 'El tipo de dispositivo seleccionado no existe.',
            'fll_estado.in'       => 'El estado debe ser Activo (1) o Inactivo (0).',
        ];
    }
}
