<?php

namespace App\Http\Requests\Carrito;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConfiguracionCuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('configuraciones_cuota');

        return [
            'cfc_prdid' => ['required', 'integer', 'exists:producto,prd_id'],

            'cfc_cantidad' => [
                'required', 'integer', 'min:1', 'max:60',
                Rule::unique('configuracion_cuota', 'cfc_cantidad')
                    ->where(fn ($q) => $q->where('cfc_prdid', $this->input('cfc_prdid')))
                    ->ignore($id, 'cfc_id'),
            ],

            'cfc_recargo_pct' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'cfc_estado'      => ['nullable', 'boolean'],
        ];
    }

    /**
     * Se valida con precioVitrina() y no con prd_pvtmin: un producto solo
     * mayorista tiene prd_pvtmin en null y quedaría bloqueado para siempre.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('cfc_prdid')) {
                return;
            }

            $producto = Producto::find($this->input('cfc_prdid'));

            if (! $producto || ! $producto->precioVitrina()) {
                $validator->errors()->add(
                    'cfc_prdid',
                    'El producto no tiene un precio de venta válido. Configure el precio antes de agregar cuotas.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'cfc_prdid.required'       => 'Falta indicar el producto.',
            'cfc_prdid.exists'         => 'El producto seleccionado no existe.',
            'cfc_cantidad.required'    => 'Debe indicar la cantidad de cuotas.',
            'cfc_cantidad.integer'     => 'La cantidad de cuotas debe ser un número entero.',
            'cfc_cantidad.min'         => 'La cantidad de cuotas debe ser mayor a 0.',
            'cfc_cantidad.max'         => 'La cantidad de cuotas no puede superar 60.',
            'cfc_cantidad.unique'      => 'Ese número de cuotas ya está configurado para este producto.',
            'cfc_recargo_pct.required' => 'Debe indicar el porcentaje de recargo.',
            'cfc_recargo_pct.numeric'  => 'El porcentaje de recargo debe ser numérico.',
            'cfc_recargo_pct.min'      => 'El recargo no puede ser negativo.',
        ];
    }
}