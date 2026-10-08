<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TarifaEnvioRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('tarifas_envio');

        return [
            'tev_sucid' => ['required', 'integer', 'exists:sucursal,suc_id'],
            'tev_ciuid' => ['required', 'integer', 'exists:ciudad,ciu_id'],
            'tev_costo' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    public function withValidator($validator): void
    {
        $id = $this->route('tarifas_envio');

        $validator->after(function ($v) use ($id) {
            if (!$this->filled('tev_sucid') || !$this->filled('tev_ciuid')) {
                return;
            }

            $existe = \App\Models\Configuracion\TarifaEnvio::where('tev_sucid', $this->tev_sucid)
                ->where('tev_ciuid', $this->tev_ciuid)
                ->when($id, fn ($q) => $q->where('tev_id', '<>', $id))
                ->exists();

            if ($existe) {
                $v->errors()->add('tev_ciuid', 'Ya existe una tarifa para esa sucursal y ciudad.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'tev_sucid.required' => 'Seleccione la sucursal.',
            'tev_sucid.exists'   => 'La sucursal seleccionada no existe.',
            'tev_ciuid.required' => 'Seleccione la ciudad.',
            'tev_ciuid.exists'   => 'La ciudad seleccionada no existe.',
            'tev_costo.required' => 'Indique el costo de envío.',
            'tev_costo.numeric'  => 'El costo debe ser un número.',
            'tev_costo.min'      => 'El costo no puede ser negativo.',
        ];
    }
}