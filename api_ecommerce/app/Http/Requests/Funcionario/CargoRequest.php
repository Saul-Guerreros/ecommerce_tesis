<?php

namespace App\Http\Requests\Funcionario;

use App\Models\Cargo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CargoRequest extends FormRequest
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
            'crg_descr' => [
                'required', 'string', 'max:80',
                // La tabla no tiene índice único: se valida aquí, sin distinguir mayúsculas.
                function (string $attribute, mixed $value, \Closure $fail) use ($id) {
                    $existe = Cargo::whereRaw('LOWER(crg_descr) = ?', [mb_strtolower(trim((string) $value))])
                        ->when($id, fn($q) => $q->where('crg_id', '!=', $id))
                        ->exists();

                    if ($existe) {
                        $fail('Ya existe un cargo con esa descripción.');
                    }
                },
            ],
            'crg_estado' => ['nullable', 'integer', Rule::in([0, 1])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('crg_descr')) {
            $this->merge(['crg_descr' => trim((string) $this->input('crg_descr'))]);
        }
    }

    public function messages(): array
    {
        return [
            'crg_descr.required' => 'La descripción es obligatoria.',
            'crg_descr.max'      => 'La descripción no puede superar los 80 caracteres.',
            'crg_estado.in'      => 'El estado debe ser Activo (1) o Inactivo (0).',
        ];
    }
}
