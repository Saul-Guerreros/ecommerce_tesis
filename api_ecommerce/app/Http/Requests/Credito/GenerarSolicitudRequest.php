<?php

namespace App\Http\Requests\Credito;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paso 4: [GENERAR SOLICITUD].
 *
 * Lo unico que viaja es la declaracion de veracidad. Los montos NO se
 * envian: el servicio los recalcula contra el carrito a credito vigente
 * al momento de generar. Si el precio de un producto cambio entre que
 * el cliente abrio el wizard y apreto el boton, manda el precio nuevo,
 * no el que quedo pintado en la pantalla. Mismo criterio que
 * PedidoService::confirmar.
 *
 * "accepted" y no "boolean": el checkbox tildado es condicion para
 * generar, no un dato opcional. Un false explicito devuelve 422.
 */
class GenerarSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scr_declar' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'scr_declar.required' => 'Tenés que declarar que los datos proporcionados son veraces.',
            'scr_declar.accepted' => 'Tenés que declarar que los datos proporcionados son veraces.',
        ];
    }
}