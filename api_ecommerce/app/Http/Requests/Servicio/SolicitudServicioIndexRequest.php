<?php

namespace App\Http\Requests\Servicio;

use Illuminate\Foundation\Http\FormRequest;

class SolicitudServicioIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buscar'          => ['nullable', 'string', 'max:100'],
            'ets_id'          => ['nullable', 'integer'],
            'elg_id'          => ['nullable', 'integer'],
            'ten_id'          => ['nullable', 'integer'],
            'garantia'        => ['nullable', 'in:0,1'],
            'suc_id'          => ['nullable', 'integer'],
            'canal'           => ['nullable', 'in:WEB,MOSTRADOR'],
            'solo_pendientes' => ['nullable', 'boolean'],
            'desde'           => ['nullable', 'date'],
            'hasta'           => ['nullable', 'date', 'after_or_equal:desde'],
            'page'            => ['nullable', 'integer', 'min:1'],
            'per_page'        => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return ['hasta.after_or_equal' => 'La fecha "hasta" no puede ser anterior a "desde".'];
    }
}
