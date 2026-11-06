<?php

namespace App\Http\Requests\Cliente;

use App\Services\Cliente\ClienteRapidoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Alta rápida de cliente en el mostrador (sin credenciales). Solo personas físicas: C.I. o pasaporte. */
class ClienteRapidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'es_whatsapp' => $this->boolean('es_whatsapp'),
            'correo'      => $this->filled('correo') ? trim((string) $this->input('correo')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'per_tpdid'   => ['required', 'integer', Rule::exists('tipo_documento', 'tpd_id')
                ->where('tpd_estado', 1)
                ->whereIn('tpd_codigo', ClienteRapidoService::DOCUMENTOS_MOSTRADOR)],
            'per_cedula'  => ['required', 'string', 'max:20', 'regex:/^[0-9A-Za-z\.\-\s]{4,20}$/'],
            'per_nombr1'  => ['required', 'string', 'max:45'],
            'per_nombr2'  => ['nullable', 'string', 'max:45'],
            'per_apell1'  => ['required', 'string', 'max:50'],
            'per_apell2'  => ['nullable', 'string', 'max:50'],
            'celular'     => ['required', 'string', 'regex:/^\+?[0-9\s\-]{6,20}$/'],
            'es_whatsapp' => ['boolean'],
            'correo'      => ['nullable', 'email', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'per_tpdid.required'  => 'Seleccione el tipo de documento.',
            'per_tpdid.exists'    => 'En el mostrador solo se registran personas físicas (C.I. o pasaporte).',
            'per_cedula.required' => 'El número de documento es obligatorio.',
            'per_cedula.regex'    => 'El número de documento no tiene un formato válido.',
            'per_nombr1.required' => 'El primer nombre es obligatorio.',
            'per_apell1.required' => 'El primer apellido es obligatorio.',
            'celular.required'    => 'El celular es obligatorio (se usa para coordinar el servicio).',
            'celular.regex'       => 'El celular no tiene un formato válido.',
            'correo.email'        => 'El correo no tiene un formato válido.',
        ];
    }
}
