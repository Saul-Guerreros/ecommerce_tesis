<?php

namespace App\Http\Requests\Servicio;

use App\Models\Servicio\TipoDispositivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorregirClasificacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tdi_id' => ['required', 'integer', Rule::exists('tipo_dispositivo', 'tdi_id')
                ->where('tdi_estado', 1)->whereNot('tdi_codigo', TipoDispositivo::PENDIENTE)],
            'caf_id' => ['required', 'integer', Rule::exists('categoria_falla', 'caf_id')->where('caf_estado', 1)],
            'urg_id' => ['required', 'integer', Rule::exists('urgencia_servicio', 'urg_id')->where('urg_estado', 1)],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'tdi_id.required' => 'Seleccione el tipo de dispositivo.',
            'tdi_id.exists'   => 'El tipo de dispositivo no es válido.',
            'caf_id.required' => 'Seleccione el tipo de problema.',
            'caf_id.exists'   => 'El tipo de problema no es válido.',
            'urg_id.required' => 'Seleccione la urgencia.',
            'urg_id.exists'   => 'La urgencia no es válida.',
            'motivo.max'      => 'El motivo no puede superar los 255 caracteres.',
        ];
    }
}