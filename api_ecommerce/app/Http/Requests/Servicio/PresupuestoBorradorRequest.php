<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T3 - Borrador del presupuesto. El precio es opcional: si no viene, el
 * servidor toma el de lista (trabajo.trb_precio o el precio de venta del
 * producto). Los subtotales/IVA SIEMPRE se calculan en el servidor.
 */
class PresupuestoBorradorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pse_obs'           => ['nullable', 'string', 'max:500'],
            'lineas'            => ['required', 'array', 'min:1', 'max:30'],
            'lineas.*.tipo'     => ['required', 'in:REPUESTO,MANO_OBRA'],
            'lineas.*.prd_id'   => ['nullable', 'required_if:lineas.*.tipo,REPUESTO', 'integer'],
            'lineas.*.trb_id'   => ['nullable', 'required_if:lineas.*.tipo,MANO_OBRA', 'integer'],
            'lineas.*.descri'   => ['nullable', 'string', 'max:150'],
            'lineas.*.cantid'   => ['required', 'numeric', 'gt:0', 'max:9999'],
            'lineas.*.precio'   => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'lineas.required'            => 'Agregue al menos una línea al presupuesto.',
            'lineas.min'                 => 'Agregue al menos una línea al presupuesto.',
            'lineas.max'                 => 'Máximo 30 líneas por presupuesto.',
            'lineas.*.prd_id.required_if' => 'Seleccione el repuesto.',
            'lineas.*.trb_id.required_if' => 'Seleccione el trabajo.',
            'lineas.*.cantid.required'   => 'Indique la cantidad.',
            'lineas.*.cantid.gt'         => 'La cantidad debe ser mayor a 0.',
            'lineas.*.precio.min'        => 'El precio no puede ser negativo.',
            'lineas.*.descri.max'        => 'La descripción admite hasta 150 caracteres.',
            'pse_obs.max'                => 'La observación no puede superar los 500 caracteres.',
        ];
    }
}
