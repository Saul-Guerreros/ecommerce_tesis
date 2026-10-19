<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los parametros de la bandeja de pedidos.
 *
 * Un solo Request compartido por index() y exportar(), igual que el
 * estandar comparte un Request entre store y update: ambos endpoints
 * reciben exactamente los mismos filtros.
 *
 * authorize() devuelve true: la autorizacion vive en el middleware
 * 'permiso:' de routes/api.php.
 */
class PedidoIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Busqueda (excluyente, ver withValidator)
            'num'     => ['nullable', 'string', 'max:30'],
            'ci'      => ['nullable', 'string', 'max:20'],
            'cliente' => ['nullable', 'string', 'max:100'],

            // Filtros: se valida que el id exista, no solo que sea entero.
            'estado_pedido' => ['nullable', 'integer', 'exists:estado_pedido,est_id'],
            'estado_pago'   => ['nullable', 'integer', 'exists:estado_pago,epa_id'],
            'tipo_entrega'  => ['nullable', 'integer', 'exists:tipo_entrega,ten_id'],
            'metodo_pago'   => ['nullable', 'integer', 'exists:metodo_pago,mpa_id'],

            // Atajo de la alerta "Retiros vencidos": no es un id de catálogo,
            // por eso es un booleano aparte y no un valor más de estado_pedido.
            'retiro_vencido' => ['nullable', 'boolean'],

            // Rango de fechas sobre ped_creado
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],

            // Paginacion
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'num.max'     => 'El número de pedido no puede superar los 30 caracteres.',
            'ci.max'      => 'La cédula no puede superar los 20 caracteres.',
            'cliente.max' => 'El nombre del cliente no puede superar los 100 caracteres.',

            'estado_pedido.exists' => 'El estado de pedido seleccionado no existe.',
            'estado_pago.exists'   => 'El estado de pago seleccionado no existe.',
            'tipo_entrega.exists'  => 'El tipo de entrega seleccionado no existe.',
            'metodo_pago.exists'   => 'El método de pago seleccionado no existe.',

            'desde.date_format' => 'La fecha "Desde" no tiene un formato válido.',
            'hasta.date_format' => 'La fecha "Hasta" no tiene un formato válido.',
            'hasta.after_or_equal' => 'La fecha "Hasta" no puede ser anterior a la fecha "Desde".',

            'per_page.max' => 'No se pueden solicitar más de 100 pedidos por página.',
        ];
    }

    /**
     * Regla principal de busqueda: un solo criterio a la vez.
     *
     * El frontend ya deshabilita los otros dos campos, pero eso es
     * experiencia de usuario, no seguridad. La regla se valida aca
     * porque combinar los tres criterios produciria un AND que casi
     * nunca devuelve resultados y confundiria al administrador.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $usados = collect(['num', 'ci', 'cliente'])
                ->filter(fn (string $campo) => trim((string) $this->input($campo)) !== '');

            if ($usados->count() > 1) {
                $v->errors()->add(
                    'num',
                    'Solo se puede buscar por un criterio a la vez: N° de pedido, cédula o nombre del cliente.'
                );
            }
        });
    }
}