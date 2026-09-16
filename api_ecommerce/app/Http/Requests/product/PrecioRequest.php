<?php

namespace App\Http\Requests\product;

use Illuminate\Foundation\Http\FormRequest;

class PrecioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Los tres campos son opcionales: null = "usar el valor general de
     * la tienda" (ver ConfiguracionPrecio). Importante: el frontend debe
     * enviar SIEMPRE las tres claves (con valor null cuando el usuario
     * elige "usar general"), no omitirlas — Laravel solo pisa el dato
     * si la clave está presente en el payload.
     *
     * prd_pvtmin/prd_pvtmay NUNCA se aceptan acá — los recalcula
     * PriceService a partir del costo promedio, igual que
     * CompraService::recalcularPrecioVenta().
     */
    public function rules(): array
    {
        return [
            'prd_marmin'    => ['nullable', 'numeric', 'min:0'],
            'prd_marmaj'    => ['nullable', 'numeric', 'min:0'],
            'prd_canminmaj' => ['nullable', 'numeric', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'prd_marmin.numeric'    => 'El margen minorista debe ser un número.',
            'prd_marmin.min'        => 'El margen minorista no puede ser negativo.',
            'prd_marmaj.numeric'    => 'El margen mayorista debe ser un número.',
            'prd_marmaj.min'        => 'El margen mayorista no puede ser negativo.',
            'prd_canminmaj.numeric' => 'La cantidad mínima mayorista debe ser un número.',
            'prd_canminmaj.min'     => 'La cantidad mínima mayorista debe ser al menos 1 unidad.',
        ];
    }
}