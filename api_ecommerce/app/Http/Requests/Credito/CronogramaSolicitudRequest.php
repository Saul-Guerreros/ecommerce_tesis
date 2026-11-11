<?php

namespace App\Http\Requests\Credito;

use Illuminate\Foundation\Http\FormRequest;

/**
 * [Recalcular]: fecha real de la primera cuota.
 *
 * No puede ser anterior a hoy (una cuota que vence en el pasado nace
 * vencida) ni mas de 90 dias hacia adelante (diferir tanto el primer
 * vencimiento suele ser un error de tipeo, no una politica).
 */
class CronogramaSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_primera_cuota' => [
                'required', 'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:' . now()->addDays(90)->format('Y-m-d'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_primera_cuota.required'        => 'Indicá la fecha de la primera cuota.',
            'fecha_primera_cuota.date_format'     => 'La fecha no tiene un formato válido.',
            'fecha_primera_cuota.after_or_equal'  => 'La primera cuota no puede vencer en el pasado.',
            'fecha_primera_cuota.before_or_equal' => 'La primera cuota no puede diferirse más de 90 días.',
        ];
    }
}