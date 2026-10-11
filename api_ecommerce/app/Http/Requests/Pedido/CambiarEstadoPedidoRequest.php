<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambio manual de estado desde el combo «Cambiar estado del pedido».
 *
 * Aca solo se valida que el codigo exista y esté activo. Si la
 * transicion es legal lo decide PedidoEstadoService: esa regla depende
 * del estado actual del pedido y del estado de sus pagos, no del
 * payload.
 */
class CambiarEstadoPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => [
                'required',
                'string',
                Rule::exists('estado_pedido', 'est_codigo')->where('est_estado', 1),
            ],
            'comentario' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'Seleccioná el nuevo estado del pedido.',
            'estado.exists'   => 'El estado seleccionado no existe o está inactivo.',
            'comentario.max'  => 'El comentario no puede superar los 500 caracteres.',
        ];
    }
}