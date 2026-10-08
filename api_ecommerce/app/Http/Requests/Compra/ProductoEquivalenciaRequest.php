<?php
// app/Http/Requests/Compra/ProductoEquivalenciaRequest.php

namespace App\Http\Requests\Compra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductoEquivalenciaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('producto_equivalencia');

        return [
            'peq_prdid'  => ['required', 'integer', 'exists:producto,prd_id'],
            'peq_umdid'  => ['required', 'integer', 'exists:unidad_medida,umd_id'],

            // Único por producto + unidad + factor (cuenta también las desactivadas).
            'peq_factor' => [
                'required', 'numeric', 'min:0.0001', 'max:999999.9999',
                Rule::unique('producto_equivalencia', 'peq_factor')
                    ->where(fn ($q) => $q->where('peq_prdid', $this->input('peq_prdid'))
                                         ->where('peq_umdid', $this->input('peq_umdid')))
                    ->ignore($id, 'peq_id'),
            ],

            'peq_estado' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    /** La unidad de stock del producto es la base (factor 1 implícito): no lleva equivalencia. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->filled('peq_prdid') || !$this->filled('peq_umdid')) {
                return;
            }

            $umdStock = DB::table('producto')->where('prd_id', $this->peq_prdid)->value('prd_umdid');

            if ($umdStock && (int) $umdStock === (int) $this->peq_umdid) {
                $validator->errors()->add(
                    'peq_umdid',
                    'Esa es la unidad de stock del producto: no necesita equivalencia (su factor es 1 implícito).'
                );
                return;
            }

            if ($validator->errors()->has('peq_factor')) {
                return;
            }

            $this->validarDuplicadoEnFamilia($validator);
        });
    }

    /**
     * Herencia (variantes): una misma presentación no puede existir a la
     * vez en el modelo base y en una de sus variantes, porque en Compras
     * aparecería dos veces en el selector.
     */
    private function validarDuplicadoEnFamilia(Validator $validator): void
    {
        $prdId   = (int) $this->peq_prdid;
        $padreId = DB::table('producto')->where('prd_id', $prdId)->value('prd_padre_id');
        $id      = $this->route('producto_equivalencia');

        $mismaPresentacion = fn () => DB::table('producto_equivalencia')
            ->where('peq_umdid', $this->peq_umdid)
            ->where('peq_factor', round((float) $this->peq_factor, 4))
            ->when($id, fn ($q) => $q->where('peq_id', '!=', $id));

        // Variante: ¿ya la hereda del modelo base?
        if ($padreId) {
            if ($mismaPresentacion()->where('peq_prdid', $padreId)->exists()) {
                $validator->errors()->add('peq_factor', 'Esta presentación ya la hereda del modelo base.');
            }
            return;
        }

        // Modelo base: ¿alguna variante ya la tiene como propia?
        $variantes = DB::table('producto_equivalencia')
            ->join('producto', 'prd_id', '=', 'peq_prdid')
            ->where('prd_padre_id', $prdId)
            ->where('peq_estado', 1)   // una desactivada no aparece en Compras
            ->where('peq_umdid', $this->peq_umdid)
            ->where('peq_factor', round((float) $this->peq_factor, 4))
            ->pluck('prd_descri');

        if ($variantes->isNotEmpty()) {
            $validator->errors()->add(
                'peq_factor',
                'Ya la tiene como propia: ' . $variantes->implode(', ')
                . '. Desactívela o elimínela ahí antes de definirla para todo el modelo.'
            );
        }
    }

    public function messages(): array
    {
        return [
            'peq_prdid.required'  => 'Falta indicar el producto.',
            'peq_umdid.required'  => 'Debe seleccionar la unidad alternativa.',
            'peq_factor.required' => 'Debe indicar la equivalencia.',
            'peq_factor.numeric'  => 'La equivalencia debe ser un número.',
            'peq_factor.min'      => 'La equivalencia debe ser mayor a 0.',
            'peq_factor.max'      => 'La equivalencia es demasiado grande.',
            'peq_factor.unique'   => 'Ya existe una unidad alternativa con la misma equivalencia.',
        ];
    }
}