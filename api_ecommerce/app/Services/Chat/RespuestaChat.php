<?php

namespace App\Services\Chat;

/** Lo que recibe el frontend después de cada mensaje. */
final class RespuestaChat
{
    public function __construct(
        public readonly string $token,
        public readonly string $texto,
        public readonly array $tarjetas,
        public readonly array $acciones,
        public readonly bool $derivada,
    ) {
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'texto' => $this->texto,
            'productos' => $this->tarjetas,
            'acciones' => $this->acciones,
            'derivada' => $this->derivada,
        ];
    }
}