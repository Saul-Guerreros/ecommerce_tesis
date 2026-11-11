<?php

namespace App\Http\Requests\Credito;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * [CONFIRMAR PEDIDO] del checkout a crédito.
 *
 * Solo viaja la facturación y la aceptación. Montos, productos y entrega NO
 * se envían: salen de la solicitud, que es lo que el cliente ya aceptó.
 * Los RUC nuevos se cargan antes desde el modal de datos fiscales, así que
 * acá un RUC siempre llega como dfi_id.
 */
class CheckoutCreditoConfirmarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_documento'     => ['required', Rule::in(['CI', 'RUC'])],
            'dfi_id'             => [Rule::requiredIf($this->input('tipo_documento') === 'RUC'), 'nullable', 'integer', 'exists:dato_fiscal,dfi_id'],
            'pfa_recibe'         => ['required', Rule::in(['CORREO', 'WHATSAPP'])],
            'acepta_condiciones' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        $aceptacion = 'Debés aceptar las condiciones de compra y los Términos y Condiciones para confirmar el pedido.';

        return [
            'tipo_documento.required'     => 'Elegí el tipo de documento.',
            'dfi_id.required'             => 'Elegí o cargá un RUC.',
            'pfa_recibe.required'         => 'Elegí cómo querés recibir tu comprobante.',
            'acepta_condiciones.required' => $aceptacion,
            'acepta_condiciones.accepted' => $aceptacion,
        ];
    }
}