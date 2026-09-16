<?php

namespace App\Http\Requests\product;

use Illuminate\Foundation\Http\FormRequest;

class ConfiguracionPrecioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A diferencia de PrecioRequest (overrides opcionales), acá los tres
     * campos son obligatorios: esta es la configuración general, el piso
     * que usan todos los productos sin override. No puede quedar en NULL.
     */
    public function rules(): array
    {
        return [
            'cpr_marmin'    => ['required', 'numeric', 'min:0'],
            'cpr_marmaj'    => ['required', 'numeric', 'min:0'],
            'cpr_canminmaj' => ['required', 'numeric', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'cpr_marmin.required'    => 'El margen minorista general es obligatorio.',
            'cpr_marmin.numeric'     => 'El margen minorista general debe ser un número.',
            'cpr_marmin.min'         => 'El margen minorista general no puede ser negativo.',
            'cpr_marmaj.required'    => 'El margen mayorista general es obligatorio.',
            'cpr_marmaj.numeric'     => 'El margen mayorista general debe ser un número.',
            'cpr_marmaj.min'         => 'El margen mayorista general no puede ser negativo.',
            'cpr_canminmaj.required' => 'La cantidad mínima mayorista general es obligatoria.',
            'cpr_canminmaj.numeric'  => 'La cantidad mínima mayorista general debe ser un número.',
            'cpr_canminmaj.min'      => 'La cantidad mínima mayorista general debe ser al menos 1 unidad.',
        ];
    }
}