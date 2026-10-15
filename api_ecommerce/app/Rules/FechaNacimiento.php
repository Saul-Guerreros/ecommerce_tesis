<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fecha de nacimiento de una persona (clientes y usuarios):
 *  - formato estricto AAAA-MM-DD y fecha real (rechaza 2024-02-31 y años de 5 dígitos)
 *  - desde el 01/01/1940
 *  - anterior a hoy (ni futura ni la fecha de hoy)
 * Si cambian los límites, actualizar también fecha-nacimiento.ts en la tienda y el admin.
 */
class FechaNacimiento implements ValidationRule
{
    public const FECHA_MINIMA = '1940-01-01';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $texto = is_string($value) ? trim($value) : '';

        // Exactamente 4 dígitos de año: bloquea "20000-01-01".
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $texto, $partes)
            || ! checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
            $fail('La fecha de nacimiento no es válida.');
            return;
        }

        // En formato AAAA-MM-DD, comparar como texto equivale a comparar fechas.
        if ($texto < self::FECHA_MINIMA) {
            $fail('La fecha de nacimiento no puede ser anterior al 01/01/1940.');
            return;
        }

        if ($texto >= Carbon::today()->toDateString()) {
            $fail('La fecha de nacimiento debe ser anterior a hoy.');
        }
    }
}