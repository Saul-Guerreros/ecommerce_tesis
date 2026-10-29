<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Servicio\TipoDispositivo;

class DiagnosticoPreliminarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
             'sse_tdiid'  => ['nullable', 'integer', Rule::exists('tipo_dispositivo', 'tdi_id')
                ->where('tdi_estado', 1)->whereNot('tdi_codigo', TipoDispositivo::PENDIENTE)],
            'sse_marca'  => ['required', 'string', 'max:50'],
            'sse_modelo' => ['required', 'string', 'max:80'],
            'sse_proble' => ['required', 'string', 'min:10', 'max:500'],
            'sse_garant' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sse_tdiid.exists'    => 'El tipo de equipo no es válido.',
            'sse_marca.required'  => 'Ingrese la marca.',
            'sse_modelo.required' => 'Ingrese el modelo.',
            'sse_proble.required' => 'Describa el problema.',
            'sse_proble.min'      => 'Describa el problema con al menos 10 caracteres.',
        ];
    }
}