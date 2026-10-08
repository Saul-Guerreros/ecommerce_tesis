<?php

namespace App\Console\Commands;

use App\Models\Chat\ChatConversacion;
use App\Services\Chat\ContextoChat;
use App\Services\Chat\Herramientas\RegistroHerramientas;
use App\Services\IA\GeminiClient;
use Illuminate\Console\Command;

/**
 * Prueba GeminiClient::conversar() con function calling.
 *   php artisan chat:probar "busco una notebook para diseño"          (herramientas simuladas)
 *   php artisan chat:probar --real "busco una notebook para diseño"   (catálogo real)
 */
class ProbarChatIA extends Command
{
    protected $signature = 'chat:probar {mensaje : Texto del cliente} {--real : Usa las herramientas reales del catálogo}';
    protected $description = 'Prueba el function calling de Gemini con herramientas simuladas o reales';

    private const INSTRUCCIONES = 'Sos el asistente de una tienda de informática en Paraguay. '
        . 'Respondé breve en español. Usá las herramientas para cualquier dato de productos; nunca inventes precios.';

    /** Vueltas máximas por turno. La última siempre responde con texto. */
    private const MAX_VUELTAS = 4;

    public function handle(GeminiClient $gemini, RegistroHerramientas $registro): int
    {
        $real = (bool) $this->option('real');
        $declaraciones = $real ? $registro->declaraciones() : $this->declaraciones();

        // Conversación SIN guardar: no deja filas de prueba en la base.
        // (update() sobre un modelo no guardado no hace nada.)
        $ctx = new ContextoChat(new ChatConversacion(['cnv_token' => 'prueba']), '127.0.0.1');

        $contenidos = [GeminiClient::turnoUsuario($this->argument('mensaje'))];
        $modelo = null;

       for ($vuelta = 1; $vuelta <= self::MAX_VUELTAS; $vuelta++) {
         $ultima = $vuelta === self::MAX_VUELTAS;
        $r = $gemini->conversar(self::INSTRUCCIONES, $contenidos, $declaraciones, $modelo,permitirHerramientas: ! $ultima);

            if ($r === null) {
                $this->error('Sin respuesta de Gemini. Revisá storage/logs/laravel.log');
                return self::FAILURE;
            }

            $modelo = $r['modelo'];   // las vueltas siguientes usan el mismo modelo
            $this->line(sprintf('Vuelta %d · %s · %d ms · tokens %d/%d · fin %s',
                $vuelta, $r['modelo'], $r['latencia_ms'], $r['uso']['entrada'], $r['uso']['salida'], $r['fin']));

            if ($r['llamadas'] === []) {
                $this->newLine();
                $this->info($r['texto']);
                return self::SUCCESS;
            }

            $contenidos[] = GeminiClient::turnoModelo($r['partes']);
            $respuestas = [];

            foreach ($r['llamadas'] as $llamada) {
                $this->comment("  → {$llamada['name']} " . json_encode($llamada['args'] ?? [], JSON_UNESCAPED_UNICODE));

                $resultado = $real
                    ? $registro->ejecutar($llamada['name'], $llamada['args'] ?? [], $ctx)
                    : null;

                $datos = $resultado?->paraModelo ?? $this->simular($llamada);

                if ($resultado) {
                    $this->line(sprintf('    %d bytes al modelo · %d tarjetas · %d acciones',
                        strlen(json_encode($datos)), count($resultado->tarjetas), count($resultado->acciones)));
                }

                $respuestas[] = GeminiClient::respuestaHerramienta($llamada, $datos);
            }

            $contenidos[] = GeminiClient::turnoRespuestas($respuestas);
        }

        $this->warn('Se alcanzó el máximo de 3 vueltas sin respuesta final.');
        return self::FAILURE;
    }

    /** Declaraciones de las herramientas SIMULADAS (sin --real). */
    private function declaraciones(): array
    {
        return [
            [
                'name' => 'buscar_productos',
                'description' => 'Busca productos de la tienda por necesidad, marca o precio.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['consulta' => ['type' => 'string']],
                    'required' => ['consulta'],
                ],
            ],
            [
                'name' => 'ver_producto',
                'description' => 'Precio, stock y ficha técnica de UN producto por su id.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['producto_id' => ['type' => 'integer']],
                    'required' => ['producto_id'],
                ],
            ],
        ];
    }

    /** Datos fijos: solo para probar el ida y vuelta con la API. */
    private function simular(array $llamada): array
    {
        return match ($llamada['name']) {
            'buscar_productos' => [
                ['id' => 101, 'titulo' => 'Notebook Lenovo IdeaPad 5 Ryzen 7 16GB', 'precio_final' => 5200000],
                ['id' => 102, 'titulo' => 'Notebook ASUS Vivobook 15 Core i5 8GB', 'precio_final' => 3900000],
            ],
            'ver_producto' => match ((int) ($llamada['args']['producto_id'] ?? 0)) {
                101 => ['id' => 101, 'precio_final' => 5200000, 'stock' => 3,
                        'especificaciones' => ['Procesador' => 'Ryzen 7 7730U', 'RAM' => '16 GB', 'Pantalla' => '15.6" FHD']],
                102 => ['id' => 102, 'precio_final' => 3900000, 'stock' => 0,
                        'especificaciones' => ['Procesador' => 'Core i5 1235U', 'RAM' => '8 GB', 'Pantalla' => '15.6" FHD']],
                default => ['error' => 'Producto no encontrado'],
            },
            default => ['error' => 'Herramienta desconocida'],
        };
    }
}