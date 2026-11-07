<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Enlace a una red social: solo https y solo los dominios indicados.
 * Evita guardar "javascript:..." o enlaces a sitios de phishing que
 * después la tienda mostraría como si fueran de la empresa.
 */
class UrlRedSocial implements ValidationRule
{
    /** @param string[] $dominios ej. ['facebook.com', 'fb.com'] */
    public function __construct(private array $dominios) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail('Ingresá un enlace válido que empiece con https://');
            return;
        }

        $partes = parse_url($value);

        if (strtolower($partes['scheme'] ?? '') !== 'https') {
            $fail('El enlace debe empezar con https://');
            return;
        }

        $host = preg_replace('/^(www\.|m\.)/', '', strtolower($partes['host'] ?? ''));

        if (! in_array($host, $this->dominios, true)) {
            $fail('El enlace debe ser de ' . implode(' o ', $this->dominios) . '.');
        }
    }
}