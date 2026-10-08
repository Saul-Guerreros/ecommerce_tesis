<?php

namespace App\Services\Chat;

/**
 * Resultado de una herramienta:
 *  - paraModelo: resumen que se envía a Gemini (compacto, sin imágenes).
 *  - tarjetas:   productos completos para el frontend (formato de la vitrina).
 *  - acciones:   botones que el cliente confirma (ej. agregar al carrito).
 */
final class ResultadoHerramienta
{
    public function __construct(
        public readonly array $paraModelo,
        public readonly array $tarjetas = [],
        public readonly array $acciones = [],
        public readonly ?string $motivoDerivacion = null,
    ) {
    }

    public static function error(string $mensaje): self
    {
        return new self(['error' => $mensaje]);
    }
}