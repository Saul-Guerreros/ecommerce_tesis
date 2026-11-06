<?php

namespace App\Http\Requests\Servicio;

use App\Services\Facturacion\FacturaEnvioService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Reenvío de la factura del servicio. Mismas reglas que el reenvío del pedido. */
class ReenviarFacturaServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'canal'   => ['nullable', Rule::in([FacturaEnvioService::CANAL_CORREO, FacturaEnvioService::CANAL_WHATSAPP])],
            'destino' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'canal.in'    => 'Canal de envío no válido.',
            'destino.max' => 'El destino es demasiado largo.',
        ];
    }
}
