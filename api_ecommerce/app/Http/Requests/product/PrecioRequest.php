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
     * Unico campo editable desde esta pantalla: el margen. prd_prevta
     * NUNCA se acepta aca tampoco — lo recalcula el controlador a partir
     * del costo promedio global, igual que CompraService::recalcularPrecioVenta().
     */
    public function rules(): array
    {
        return [
            'prd_margen' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'prd_margen.required' => 'El margen es obligatorio.',
            'prd_margen.numeric'  => 'El margen debe ser un número.',
            'prd_margen.min'      => 'El margen no puede ser negativo.',
        ];
    }
}