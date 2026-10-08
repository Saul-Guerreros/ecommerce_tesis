<?php

namespace App\Http\Requests\Cliente;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DatoFiscalClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza dfi_alias igual que DireccionClienteRequest: recorta
     * extremos y colapsa espacios internos antes de validar.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('dfi_alias')) {
            $this->merge([
                'dfi_alias' => preg_replace('/\s+/u', ' ', trim((string) $this->input('dfi_alias'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'dfi_alias'    => ['required', 'string', 'max:50'],
            // RUC paraguayo: hasta 15 digitos, guion, y el digito
            // verificador (un solo digito, ej: 5690649-7). Mismo patron
            // que cliente.cli_ruc (vease PerfilClienteRequest) para que
            // RucParaguayo::correspondeACedula() pueda parsear el campo
            // con el mismo regex en ambos lugares.
            'dfi_numero'   => ['required', 'string', 'max:20', 'regex:/^[0-9]{1,15}-[0-9]$/'],
            'dfi_razon'    => ['required', 'string', 'max:150'],
            'dfi_telefono' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{6,20}$/'],
            'dfi_correo'   => ['required', 'email', 'max:100'],
            'dfi_principal' => ['nullable', 'boolean'],
        ];
    }

    /**
     * El alias debe ser unico POR CLIENTE, con el mismo criterio que
     * DireccionClienteRequest: alcance resuelto por el token (no por un
     * campo del body) y comparacion insensible a mayusculas/acentos.
     * Vease ese archivo para el detalle de por que no se usa la regla
     * `unique` de Laravel.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('dfi_alias')) {
                return;
            }

            if ($this->aliasYaRegistrado((string) $this->input('dfi_alias'))) {
                $validator->errors()->add(
                    'dfi_alias',
                    'Ya tenés un RUC guardado con ese nombre. Elegí otro.'
                );
            }
        });
    }

    private function aliasYaRegistrado(string $alias): bool
    {
        $persona = auth('api')->user()?->persona;

        if (!$persona) {
            return false;
        }

        $idActual = $this->route('id'); // null en store, dfi_id en update

        return $persona->datosFiscales()
            ->whereRaw(
                'public.normalizar_alias(dfi_alias) = public.normalizar_alias(?)',
                [$alias]
            )
            ->when($idActual, fn ($q) => $q->where('dfi_id', '<>', $idActual))
            ->exists();
    }

    public function messages(): array
    {
        return [
            'dfi_alias.required'    => 'Ponele un nombre a este RUC (ej: Mi RUC).',
            'dfi_alias.max'         => 'El nombre no puede superar los 50 caracteres.',
            'dfi_numero.required'   => 'El número de RUC es obligatorio.',
            'dfi_numero.regex'      => 'El RUC debe tener el formato 1234567-8.',
            'dfi_razon.required'    => 'La razón social es obligatoria.',
            'dfi_telefono.required' => 'El teléfono es obligatorio.',
            'dfi_telefono.regex'    => 'El teléfono solo admite números, espacios y guiones.',
            'dfi_correo.required'   => 'El correo es obligatorio.',
            'dfi_correo.email'      => 'El correo no tiene un formato válido.',
        ];
    }
}