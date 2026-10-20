<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El cliente sube un comprobante nuevo para un pago rechazado.
 *
 * Mucho más chico que PagoPedidoRequest: acá no se elige método ni monto
 * (los fijó el pago original y no pueden cambiar), sólo viene el archivo.
 * Permitir que el cliente cambiara el método o el monto en esta instancia
 * sería reescribir un pago ya declarado, no corregir su comprobante.
 *
 * A diferencia del Paso 3, el archivo es obligatorio: no hay nada que
 * conservar, el comprobante anterior es justamente el que se rechazó.
 */
class ReemplazarComprobanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comprobante' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'comprobante.required' => 'Adjuntá el comprobante corregido.',
            'comprobante.file'     => 'El comprobante debe ser un archivo válido.',
            'comprobante.mimes'    => 'El comprobante debe ser una imagen (jpg, png) o PDF.',
            'comprobante.max'      => 'El comprobante no puede superar los 5 MB.',
        ];
    }
}