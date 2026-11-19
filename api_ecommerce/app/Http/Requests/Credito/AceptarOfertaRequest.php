<?php

namespace App\Http\Requests\Credito;

use Illuminate\Foundation\Http\FormRequest;

/**
 * [ACEPTAR] de la oferta de crédito aprobada.
 *
 * Solo viajan las dos declaraciones de los checkboxes. Los montos y el
 * cronograma NO se envían: son los que el administrador congeló al
 * aprobar, y el cliente solo los acepta tal cual están.
 *
 * "accepted" y no "boolean": el checkbox tildado es condición para
 * aceptar. Un false explícito devuelve 422 (la validación del frontend es
 * experiencia de usuario; esta es la real).
 */
class AceptarOfertaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'acepta_condiciones' => ['required', 'accepted'],
            'acepta_firma'       => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'acepta_condiciones.required' => 'Tenés que aceptar las condiciones del crédito y el cronograma de cuotas.',
            'acepta_condiciones.accepted' => 'Tenés que aceptar las condiciones del crédito y el cronograma de cuotas.',
            'acepta_firma.required'       => 'Tenés que comprometerte a firmar la documentación al recibir el producto.',
            'acepta_firma.accepted'       => 'Tenés que comprometerte a firmar la documentación al recibir el producto.',
        ];
    }
}