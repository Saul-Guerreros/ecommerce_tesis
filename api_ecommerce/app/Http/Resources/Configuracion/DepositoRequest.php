<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepositoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Solo forma de los datos. Las reglas que dependen del ESTADO del
     * sistema (auto-asignar principal al primer depósito, impedir dejar
     * una sucursal sin principal, bloquear desactivar/borrar el principal)
     * NO van acá — el Estándar las ubica en el Controlador porque requieren
     * consultar otros registros, no solo el payload actual.
     */
    public function rules(): array
    {
        $id = $this->route('deposito');

        return [
            'dpo_sucid' => ['required', 'integer', 'exists:sucursal,suc_id'],
            'dpo_codigo' => [
                'required',
                'string',
                'max:10',
                // UNIQUE compuesto (dpo_sucid, dpo_codigo): el código se
                // repite entre sucursales distintas, no dentro de la misma.
                Rule::unique('deposito', 'dpo_codigo')
                    ->where(fn ($q) => $q->where('dpo_sucid', $this->input('dpo_sucid')))
                    ->ignore($id, 'dpo_id'),
            ],
            'dpo_nombre' => ['required', 'string', 'max:45'],
            'dpo_descri' => ['nullable', 'string', 'max:45'],
            'dpo_princi' => ['nullable', 'integer', 'in:0,1'],
            'dpo_estado' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'dpo_sucid.required'  => 'Debe seleccionar la sucursal.',
            'dpo_sucid.exists'    => 'La sucursal seleccionada no existe.',
            'dpo_codigo.required' => 'El código del depósito es obligatorio.',
            'dpo_codigo.unique'   => 'Esa sucursal ya tiene un depósito con ese código.',
            'dpo_codigo.max'      => 'El código no puede superar los 10 caracteres.',
            'dpo_nombre.required' => 'El nombre del depósito es obligatorio.',
            'dpo_nombre.max'      => 'El nombre no puede superar los 45 caracteres.',
        ];
    }
}