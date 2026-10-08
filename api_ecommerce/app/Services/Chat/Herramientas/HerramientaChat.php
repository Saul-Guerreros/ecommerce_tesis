<?php

namespace App\Services\Chat\Herramientas;

use App\Services\Chat\ContextoChat;
use App\Services\Chat\ResultadoHerramienta;

interface HerramientaChat
{
    /** Nombre que usa el modelo para invocarla (snake_case). */
    public function nombre(): string;

    /** functionDeclaration para Gemini: name, description y parameters. */
    public function declaracion(): array;

    public function ejecutar(array $args, ContextoChat $ctx): ResultadoHerramienta;
}