<?php

namespace App\Http\Requests\Compra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

/** Compartido entre store y update. */
class TimbradoProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tpv_proid'  => ['required', 'integer', 'exists:proveedor,pro_id'],
            'tpv_tcoid'  => ['required', 'integer', 'exists:tipo_comprobante,tco_id'],
            'tpv_numero' => ['required', 'digits_between:1,8'],
            'tpv_fechin' => ['required', 'date_format:Y-m-d'],
            'tpv_fechfn' => ['required', 'date_format:Y-m-d', 'after_or_equal:tpv_fechin'],
        ];
    }

    /**
     * UNIQUE (proveedor, tipo, numero) validado aca porque el numero se
     * normaliza con ceros a la izquierda antes de comparar ("4" = "00000004").
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) return;

            // Nombre del parametro que genera apiResource('timbrados-proveedor').
            $id = $this->route('timbrados_proveedor');

            $existe = DB::table('timbrado_proveedor')
                ->where('tpv_proid', $this->tpv_proid)
                ->where('tpv_tcoid', $this->tpv_tcoid)
                ->where('tpv_numero', str_pad($this->tpv_numero, 8, '0', STR_PAD_LEFT))
                ->when($id, fn ($q) => $q->where('tpv_id', '!=', $id))
                ->exists();

            if ($existe) {
                $validator->errors()->add('tpv_numero', 'Ese timbrado ya está registrado para este proveedor y tipo de comprobante.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'tpv_tcoid.required'        => 'Debe seleccionar el tipo de comprobante.',
            'tpv_numero.required'       => 'El número de timbrado es obligatorio.',
            'tpv_numero.digits_between' => 'El timbrado debe tener hasta 8 dígitos numéricos.',
            'tpv_fechin.required'       => 'La fecha de inicio de vigencia es obligatoria.',
            'tpv_fechfn.required'       => 'La fecha de vencimiento es obligatoria.',
            'tpv_fechfn.after_or_equal' => 'El vencimiento no puede ser anterior al inicio de vigencia.',
        ];
    }
}