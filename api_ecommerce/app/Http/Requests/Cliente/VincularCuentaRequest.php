<?php

namespace App\Http\Requests\Cliente;

use App\Services\Cliente\ClienteRapidoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Crear cuenta web con el código entregado en la sucursal. */
class VincularCuentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', Rule::in(ClienteRapidoService::DOCUMENTOS_MOSTRADOR)],
            'cedula'         => ['required', 'string', 'max:20', 'regex:/^[0-9A-Za-z\.\-\s]{4,20}$/'],
            'codigo'         => ['required', 'string', 'max:20'],
            'email'          => ['required', 'email', 'max:100'],
            'password'       => ['required', 'string', 'min:8', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_documento.in' => 'Seleccioná C.I. o pasaporte.',
            'cedula.required'   => 'Ingresá tu número de documento.',
            'cedula.regex'      => 'El número de documento no tiene un formato válido.',
            'codigo.required'   => 'Ingresá el código de vinculación de tu constancia.',
            'email.required'    => 'Ingresá tu correo electrónico.',
            'email.email'       => 'El correo no tiene un formato válido.',
            'password.min'      => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}