<?php

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
            'peq_umdid'  => [
                'required', 'integer', 'exists:unidad_medida,umd_id',
                Rule::unique('producto_equivalencia', 'peq_umdid')
                    ->where(fn ($q) => $q->where('peq_prdid', $this->input('peq_prdid')))
                    ->ignore($id, 'peq_id'),
            ],
            'peq_factor' => ['required', 'numeric', 'min:0.0001'],
            'peq_estado' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    /**
     * La unidad alternativa no puede ser la misma unidad de stock del
     * producto: esa ya es la base, con factor 1 implícito.
     */
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
            }
        });
    }

    public function messages(): array
    {
        return [
            'peq_prdid.required'  => 'Falta indicar el producto.',
            'peq_umdid.required'  => 'Debe seleccionar la unidad alternativa.',
            'peq_umdid.unique'    => 'Este producto ya tiene una equivalencia registrada para esa unidad.',
            'peq_factor.required' => 'Debe indicar el factor de conversión.',
            'peq_factor.min'      => 'El factor debe ser mayor a 0.',
        ];
    }
}