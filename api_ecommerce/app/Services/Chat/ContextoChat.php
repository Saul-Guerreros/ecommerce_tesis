<?php

namespace App\Services\Chat;

use App\Models\Chat\ChatConversacion;

/**
 * Quién está preguntando. Las herramientas toman de acá la identidad
 * (cliente, IP), NUNCA de los argumentos del modelo.
 */
final class ContextoChat
{
    public function __construct(
        public readonly ChatConversacion $conversacion,
        public readonly ?string $ip,
       public readonly ?int $usuId = null,
    ) {
    }
}