<?php

namespace App\Http\Requests\Servicio;

use App\Models\Pedido\EstadoPago;
use App\Models\Servicio\ServicioPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filtros de la bandeja "Cobros de servicios". */
class CobroServicioIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buscar'   => ['nullable', 'string', 'max:100'],
            'estado'   => ['nullable', Rule::in([EstadoPago::PAGO_VERIFICAR, EstadoPago::CONFIRMADO, EstadoPago::RECHAZADO])],
            'canal'    => ['nullable', Rule::in([ServicioPago::CANAL_WEB, ServicioPago::CANAL_MOSTRADOR])],
            'mpa_id'   => ['nullable', 'integer'],
            'suc_id'   => ['nullable', 'integer'],
            'desde'    => ['nullable', 'date'],
            'hasta'    => ['nullable', 'date', 'after_or_equal:desde'],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return ['hasta.after_or_equal' => 'La fecha "hasta" no puede ser anterior a "desde".'];
    }
}
