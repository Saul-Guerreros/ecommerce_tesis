<?php

namespace App\Http\Requests\Servicio;

use App\Models\Servicio\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * T4 - Cierre. NO_REPARABLE no se admite acá: ese cierre es del diagnóstico
 * (Paso 3) o del rechazo del presupuesto (Paso 4).
 */
class FinalizarOrdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ose_proced'  => ['required', 'string', 'min:10', 'max:5000'],
            'ose_result'  => ['required', Rule::in([OrdenServicio::REPARADO, OrdenServicio::PARCIAL])],
            'ose_gardia'  => ['required', 'integer', 'min:0', 'max:730'],
            'repuestos'   => ['nullable', 'array'],
            'repuestos.*' => ['numeric', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'ose_proced.required' => 'Describa los procedimientos y pruebas realizados.',
            'ose_proced.min'      => 'El detalle debe tener al menos 10 caracteres.',
            'ose_proced.max'      => 'El detalle no puede superar los 5000 caracteres.',
            'ose_result.required' => 'Indique el resultado de la reparación.',
            'ose_result.in'       => 'El resultado debe ser Reparado o Parcial.',
            'ose_gardia.required' => 'Indique los días de garantía del trabajo (0 si no tiene).',
            'ose_gardia.min'      => 'La garantía no puede ser negativa.',
            'ose_gardia.max'      => 'La garantía no puede superar 730 días.',
            'repuestos.*.min'     => 'La cantidad no puede ser negativa.',
        ];
    }
}
