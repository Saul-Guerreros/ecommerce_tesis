<?php

namespace App\Console\Commands;

use App\Services\Catalogo\InterpreteConsultaService;
use App\Services\IA\GeminiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Prueba la capa LLM del buscador desde la consola, sin pasar por la tienda:
 *
 *   php artisan busqueda:probar-ia "laptop"
 *   php artisan busqueda:probar-ia "notebook de menos de 4 millones"
 *
 * Muestra los modelos configurados y el JSON que devolvió Gemini (ya
 * validado). Si falla, el motivo exacto queda en storage/logs/laravel.log.
 */
class ProbarBusquedaIA extends Command
{
    protected $signature = 'busqueda:probar-ia {consulta : Texto a interpretar}';

    protected $description = 'Prueba la interpretación de una consulta con Gemini';

    public function handle(InterpreteConsultaService $interprete, GeminiClient $gemini): int
    {
        $consulta = $this->argument('consulta');

        if (! $gemini->disponible()) {
            $this->error('Falta GEMINI_API_KEY en el .env (y luego php artisan config:clear).');
            return self::FAILURE;
        }

        $this->line('Modelos (en orden): ' . implode(' -> ', $gemini->modelos()));

        // Sin caché: siempre se consulta al modelo.
        Cache::forget(InterpreteConsultaService::claveCache($consulta));

        $inicio = microtime(true);
        $resultado = $interprete->interpretar($consulta, 'consola');
        $ms = (int) ((microtime(true) - $inicio) * 1000);

        if (! $resultado) {
            $this->error("Sin respuesta de Gemini ({$ms} ms). Revisá storage/logs/laravel.log");
            return self::FAILURE;
        }

        $this->info("Interpretación ({$ms} ms):");
        $this->line(json_encode($resultado->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
