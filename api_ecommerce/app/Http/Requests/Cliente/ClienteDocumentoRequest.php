<?php

namespace App\Http\Requests\Cliente;

use App\Services\Cliente\ClienteRapidoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Completar el documento de un cliente que no lo tiene (cuentas web registradas sin cédula). */
class ClienteDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_tpdid'  => ['required', 'integer', Rule::exists('tipo_documento', 'tpd_id')
                ->where('tpd_estado', 1)
                ->whereIn('tpd_codigo', ClienteRapidoService::DOCUMENTOS_MOSTRADOR)],
            'per_cedula' => ['required', 'string', 'max:20', 'regex:/^[0-9A-Za-z\.\-\s]{4,20}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'per_tpdid.required'  => 'Seleccione el tipo de documento.',
            'per_tpdid.exists'    => 'Solo se admite C.I. o pasaporte.',
            'per_cedula.required' => 'El número de documento es obligatorio.',
            'per_cedula.regex'    => 'El número de documento no tiene un formato válido.',
        ];
    }
}
