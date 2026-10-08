<?php

namespace App\Services\Chat\Herramientas;

use App\Services\Chat\ContextoChat;
use App\Services\Chat\ResultadoHerramienta;
use Illuminate\Support\Facades\Log;

/**
 * Lista blanca de herramientas del chatbot. Si el modelo pide una que no
 * está acá, no se ejecuta nada. Un fallo interno de una herramienta se
 * convierte en un mensaje para el modelo: el chat nunca devuelve un 500.
 */
class RegistroHerramientas
{
    /** @var array<string, HerramientaChat> */
    private array $herramientas = [];

    /** @param iterable<HerramientaChat> $herramientas */
    public function __construct(iterable $herramientas)
    {
        foreach ($herramientas as $herramienta) {
            $this->herramientas[$herramienta->nombre()] = $herramienta;
        }
    }

    public function declaraciones(): array
    {
        return array_values(array_map(fn (HerramientaChat $h) => $h->declaracion(), $this->herramientas));
    }

    public function ejecutar(string $nombre, array $args, ContextoChat $ctx): ResultadoHerramienta
    {
        $herramienta = $this->herramientas[$nombre] ?? null;

        if (! $herramienta) {
            Log::warning("Chat: el modelo pidió una herramienta inexistente: {$nombre}");
            return ResultadoHerramienta::error("La herramienta {$nombre} no existe.");
        }

        try {
            return $herramienta->ejecutar($args, $ctx);
        } catch (\Throwable $e) {
            Log::error("Chat: falló la herramienta {$nombre}", ['args' => $args, 'error' => $e->getMessage()]);
            return ResultadoHerramienta::error('No se pudo obtener esa información en este momento.');
        }
    }
}