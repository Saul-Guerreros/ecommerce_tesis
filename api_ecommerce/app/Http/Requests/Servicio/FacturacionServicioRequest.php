<?php

namespace App\Http\Requests\Servicio;

use App\Models\Servicio\ServicioFacturacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos fiscales del servicio (tienda y panel). Valida la FORMA; las reglas
 * de negocio (RUC con dígito verificador, dato fiscal del cliente, canal con
 * su dato, factura ya emitida) las aplica FacturacionServicioService.
 *
 *   tipo_documento CI  -> se usa la cédula del cliente (no viaja el número)
 *   tipo_documento RUC -> dfi_id (RUC guardado) o nrodoc + raznom (uso único)
 */
class FacturacionServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', Rule::in([ServicioFacturacion::TIPO_CI, ServicioFacturacion::TIPO_RUC])],
            'recibe'         => ['required', Rule::in([ServicioFacturacion::RECIBE_CORREO, ServicioFacturacion::RECIBE_WHATSAPP])],
            'dfi_id'         => ['nullable', 'integer', 'min:1'],
            'nrodoc'         => ['nullable', 'string', 'max:20'],
            'raznom'         => ['nullable', 'string', 'max:150'],
            'telefono'       => ['nullable', 'string', 'max:45'],
            'correo'         => ['nullable', 'email', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_documento.required' => 'Elegí si la factura sale con CI o con RUC.',
            'tipo_documento.in'       => 'Tipo de documento no válido.',
            'recibe.required'         => 'Elegí por dónde querés recibir la factura.',
            'recibe.in'               => 'Canal de envío no válido.',
            'correo.email'            => 'El correo no es válido.',
            'raznom.max'              => 'La razón social no puede superar los 150 caracteres.',
        ];
    }
}
