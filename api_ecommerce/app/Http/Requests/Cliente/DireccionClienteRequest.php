<?php

namespace App\Http\Requests\Cliente;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DireccionClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza el alias ANTES de validar: recorta extremos y colapsa
     * espacios internos. Asi "  Mi   Casa " llega a las reglas (y a la
     * base) como "Mi Casa", y el max:50 mide el valor real que se guarda.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('dir_alias')) {
            $this->merge([
                'dir_alias' => preg_replace('/\s+/u', ' ', trim((string) $this->input('dir_alias'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'dir_alias'      => ['required', 'string', 'max:50'],
            'dir_ciuid'      => ['required', 'integer', 'exists:ciudad,ciu_id'],
            'dir_barrio'     => ['required', 'string', 'max:60'],
            'dir_calle'      => ['required', 'string', 'max:100'],
            'dir_nrocasa'    => ['nullable', 'string', 'max:45'],
            'dir_referencia' => ['nullable', 'string', 'max:250'],
            'dir_celular'    => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{6,20}$/'],
            'dir_telef'      => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{6,20}$/'],
            'dir_predet'     => ['nullable', 'boolean'],
        ];
    }

    /**
     * El alias debe ser unico POR CLIENTE. No se usa la regla `unique`
     * de Laravel por dos motivos:
     *
     *  1. El alcance no es la tabla entera sino las direcciones del
     *     cliente autenticado (dir_perid), que no viene en el request:
     *     se resuelve del token. Dejarlo abierto permitiria que un
     *     cliente consulte los alias de otro por diferencia de errores.
     *  2. La comparacion debe ser insensible a mayusculas y acentos
     *     ("mi casa" == "Mi Càsa"), y `unique` compara segun el collation
     *     de la columna.
     *
     * En edicion se excluye la propia fila (dir_id de la ruta); de lo
     * contrario la direccion chocaria consigo misma al guardar sin
     * cambiar el nombre.
     *
     * Solo se comparan las direcciones ACTIVAS: el borrado es logico,
     * asi que una direccion eliminada no debe seguir reservando su
     * nombre. Espeja el WHERE del indice parcial ux_direccion_perid_alias.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('dir_alias')) {
                return; // ya fallo required/max: no tiene sentido chequear duplicados
            }

            if ($this->aliasYaRegistrado((string) $this->input('dir_alias'))) {
                $validator->errors()->add(
                    'dir_alias',
                    'Ya tenés una dirección guardada con ese nombre. Elegí otro.'
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

        $idActual = $this->route('id'); // null en store, dir_id en update

        return $persona->direcciones()
            ->activas()
            ->whereRaw(
                'UNACCENT(LOWER(TRIM(dir_alias))) = UNACCENT(LOWER(TRIM(?)))',
                [$alias]
            )
            ->when($idActual, fn ($q) => $q->where('dir_id', '<>', $idActual))
            ->exists();
    }

    public function messages(): array
    {
        return [
            'dir_alias.required'  => 'Ponele un nombre a esta dirección (ej: Mi Casa).',
            'dir_alias.max'       => 'El nombre no puede superar los 50 caracteres.',
            'dir_ciuid.required'  => 'Seleccioná la ciudad.',
            'dir_ciuid.exists'    => 'La ciudad seleccionada no es válida.',
            'dir_barrio.required' => 'El barrio es obligatorio.',
            'dir_calle.required'  => 'La calle es obligatoria.',
            'dir_celular.regex'   => 'El celular solo admite números, espacios y guiones.',
            'dir_telef.regex'     => 'El teléfono solo admite números, espacios y guiones.',
        ];
    }
}