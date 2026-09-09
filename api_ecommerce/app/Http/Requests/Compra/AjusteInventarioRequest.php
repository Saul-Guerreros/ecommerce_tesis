<?php

namespace App\Http\Requests\Compra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class AjusteInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'aji_tpaid'      => ['required', 'integer', 'exists:tipo_ajuste,tpa_id'],
            'aji_depid'      => ['required', 'integer', 'exists:deposito,dpo_id'],
            'aji_fecha'      => ['required', 'date'],
            'aji_referencia' => ['nullable', 'string', 'max:45'],
            'aji_obs'        => ['nullable', 'string', 'max:45'],

            'detalle'               => ['required', 'array', 'min:1'],
            'detalle.*.prd_id'      => ['required', 'integer', 'exists:producto,prd_id'],
            'detalle.*.cantfis'     => ['required', 'numeric', 'min:0'],
            'detalle.*.costo'       => ['nullable', 'numeric', 'min:0'],
            'detalle.*.obs'         => ['nullable', 'string', 'max:45'],
        ];
    }

    /**
     * Si tpa_reqcom = 1, aji_referencia pasa a ser obligatoria. La
     * validacion de consistencia P/N contra la diferencia real se hace en
     * AjusteService::confirmar(), que es donde se recaptura el stock en
     * vivo -- a esta altura (Borrador) todavia no corresponde.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->filled('aji_tpaid')) {
                return;
            }

            $tipo = DB::table('tipo_ajuste')->where('tpa_id', $this->aji_tpaid)->first();

            if ($tipo && (int) $tipo->tpa_reqcom === 1 && !$this->filled('aji_referencia')) {
                $validator->errors()->add(
                    'aji_referencia',
                    'El tipo de ajuste seleccionado exige indicar una referencia.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'aji_tpaid.required'  => 'Debe seleccionar el tipo de ajuste.',
            'aji_tpaid.exists'    => 'El tipo de ajuste seleccionado no existe.',
            'aji_depid.required'  => 'Debe seleccionar el depósito.',
            'aji_fecha.required'  => 'La fecha es obligatoria.',
            'detalle.required'    => 'Debe cargar al menos un ítem.',
            'detalle.min'         => 'Debe cargar al menos un ítem.',
            'detalle.*.prd_id.required'  => 'Falta seleccionar el producto en una de las líneas.',
            'detalle.*.cantfis.required' => 'Falta la cantidad física en una de las líneas.',
            'detalle.*.cantfis.min'      => 'La cantidad física no puede ser negativa.',
        ];
    }
}