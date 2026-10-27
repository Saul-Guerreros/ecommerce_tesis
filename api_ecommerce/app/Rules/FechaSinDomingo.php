<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fecha en que el cliente lleva o se retira el equipo (Servicio Técnico):
 * el local no atiende los domingos.
 * Si cambia la regla, actualizar también fecha-servicio.ts en la tienda y el admin.
 */
class FechaSinDomingo implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return; // vacío o inválido: ya lo reportan 'required' y 'date'
        }

        try {
            $fecha = Carbon::parse($value);
        } catch (\Throwable) {
            return;
        }

        if ($fecha->isSunday()) {
            $fail('No se puede agendar un domingo: el local no atiende ese día.');
        }
    }
}