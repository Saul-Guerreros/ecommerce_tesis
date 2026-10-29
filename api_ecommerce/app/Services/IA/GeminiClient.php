<?php

namespace App\Services\IA;

use Closure;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Cliente de la API de Gemini (Google AI).
 *  - generarJson(): respuesta JSON con esquema fijo (structured output). Buscador.
 *  - conversar():   conversación multiturno con function calling. ChatBot.
 *
 * Alta disponibilidad: si un modelo está saturado (503), con límite (429)
 * o no existe (404), se prueba el siguiente de GEMINI_MODELOS_RESPALDO.
 * Diseño defensivo: ante cualquier problema devuelve null y deja el error
 * en el log. Quien lo usa siempre tiene un camino sin IA.
 */
class GeminiClient
{
    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    private const ESTADOS_PROBAR_OTRO = [404, 429, 500, 503];
    private const PROBAR_OTRO = 'probar_otro';
    /** Con menos tiempo que esto, un intento casi seguro termina en timeout. */
    private const SEGUNDOS_MINIMOS = 5;

    public function disponible(): bool
    {
        return ! empty(config('services.gemini.key'));
    }

    /** Modelo principal + modelos de respaldo, en orden. */
    public function modelos(): array
    {
        $respaldo = array_map('trim', explode(',', (string) config('services.gemini.respaldo', '')));

        return array_values(array_unique(array_filter([config('services.gemini.model'), ...$respaldo])));
    }

    // ------------------------------------------------------------------
    // Buscador: JSON estructurado (comportamiento idéntico al anterior)
    // ------------------------------------------------------------------

	public function generarJson(string $instrucciones, string $mensaje, array $esquema, ?int $porModelo = null, ?int $total = null): ?array
    {	
        if (! $this->disponible()) {
            return null;
        }

        return $this->conRespaldo($this->modelos(), function (string $modelo, int $timeout) use ($instrucciones, $mensaje, $esquema) {
            $respuesta = $this->enviar($modelo, $timeout, [
                'systemInstruction' => ['parts' => [['text' => $instrucciones]]],
                'contents' => [self::turnoUsuario($mensaje)],
                'generationConfig' => [
                    'temperature' => 0,
                    'responseMimeType' => 'application/json',
                    'responseJsonSchema' => $esquema,
                ] + $this->razonamiento($modelo),
            ]);

            if (! $respuesta instanceof Response) {
                return $respuesta;   // null o PROBAR_OTRO
            }

            $texto = $this->textoDe($respuesta->json('candidates.0.content.parts', []));
            $datos = json_decode($texto, true);

            if (! is_array($datos)) {
                Log::warning("Gemini [{$modelo}]: la respuesta no es JSON válido", ['texto' => mb_strimwidth($texto, 0, 400)]);
                return null;
            }

            return $datos;
	    }, $porModelo ?? (int) config('services.gemini.timeout', 8), $total ?? (int) config('services.gemini.tiempo_total', 20));
    }

    // ------------------------------------------------------------------
    // ChatBot: conversación con function calling
    // ------------------------------------------------------------------

    /**
     * @param array       $contenidos           Historial (usar turnoUsuario/turnoModelo/...).
     * @param array       $declaraciones        functionDeclarations de las herramientas.
     * @param string|null $modelo               Fija el modelo (vueltas de un mismo turno).
     * @param bool        $permitirHerramientas false = el modelo DEBE responder con texto
     *                                          (última vuelta del bucle).
     * @return array{modelo: string, partes: array, llamadas: array, texto: string,
     *               fin: ?string, uso: array{entrada: int, salida: int}, latencia_ms: int}|null
     */
    public function conversar(string $instrucciones, array $contenidos, array $declaraciones = [], ?string $modelo = null, bool $permitirHerramientas = true,): ?array
    {
        if (! $this->disponible()) {
            return null;
        }

        $modelos = $modelo ? [$modelo] : $this->modelos();

        return $this->conRespaldo($modelos, function (string $modelo, int $timeout) use ($instrucciones, $contenidos, $declaraciones, $permitirHerramientas) {
            $cuerpo = [
                'systemInstruction' => ['parts' => [['text' => $instrucciones]]],
                'contents' => $contenidos,
            ];

            // Sin temperature: Gemini 3 recomienda el valor por defecto.
            // Solo se envía si hay algo: un array vacío se codifica como [] y la API exige {}.
            if ($generacion = $this->razonamiento($modelo)) {
                $cuerpo['generationConfig'] = $generacion;
            }

            if ($declaraciones) {
                $cuerpo['tools'] = [['functionDeclarations' => $declaraciones]];
                $cuerpo['toolConfig'] = ['functionCallingConfig' => ['mode' => $permitirHerramientas ? 'AUTO' : 'NONE',]];
            }

            $inicio = microtime(true);
            $respuesta = $this->enviar($modelo, $timeout, $cuerpo);

            if (! $respuesta instanceof Response) {
                return $respuesta;
            }

            $partes = $respuesta->json('candidates.0.content.parts', []);
            $fin = $respuesta->json('candidates.0.finishReason');

            if ($partes === []) {
                // Ej.: SAFETY o MALFORMED_FUNCTION_CALL. No se reintenta con otro modelo.
                Log::warning("Gemini [{$modelo}]: respuesta sin contenido", ['fin' => $fin]);
                 // STOP sin contenido es un fallo transitorio: se prueba otro modelo.
                // Un bloqueo (SAFETY, etc.) se repetiría con cualquiera: se corta.
                return $fin === 'STOP' ? self::PROBAR_OTRO : null;
            }

            return [
                'modelo' => $modelo,
                'partes' => $partes,    // crudas: se reenvían tal cual (thoughtSignature)
                'llamadas' => collect($partes)->pluck('functionCall')->filter()->values()->all(),
                'texto' => $this->textoDe($partes),
                'fin' => $fin,
                'uso' => [
                    'entrada' => (int) $respuesta->json('usageMetadata.promptTokenCount', 0),
                    // El razonamiento se cobra como salida.
                    'salida' => (int) $respuesta->json('usageMetadata.candidatesTokenCount', 0)
                              + (int) $respuesta->json('usageMetadata.thoughtsTokenCount', 0),
                ],
                'latencia_ms' => (int) round((microtime(true) - $inicio) * 1000),
            ];
        }, (int) config('services.gemini.chat_timeout', 15), (int) config('services.gemini.chat_tiempo_total', 25));
    }

    // ------------------------------------------------------------------
    // Constructores del historial (formato de la API)
    // ------------------------------------------------------------------

    public static function turnoUsuario(string $texto): array
    {
        return ['role' => 'user', 'parts' => [['text' => $texto]]];
    }

    /** Respuesta de texto del asistente guardada en el historial (turnos anteriores). */
    public static function turnoAsistente(string $texto): array
    {
        return ['role' => 'model', 'parts' => [['text' => $texto]]];
    }

    /** Turno del modelo dentro de la vuelta actual: partes SIN modificar. */
    public static function turnoModelo(array $partes): array
    {
        // Única corrección: json() convierte {} en [], y al reenviarlo la API
        // rechaza "args": []. Se restaura como objeto; la firma no se toca.
        foreach ($partes as &$parte) {
            if (isset($parte['functionCall']) && empty($parte['functionCall']['args'])) {
                $parte['functionCall']['args'] = (object) [];
            }
        }

        return ['role' => 'model', 'parts' => $partes];
    }

    /** Resultado de una herramienta, para la llamada $llamada del modelo. */
    public static function respuestaHerramienta(array $llamada, array $resultado): array
    {
        // La API exige un objeto: una lista o un array vacío se envuelven.
        $contenido = match (true) {
            $resultado === [] => (object) [],
            array_is_list($resultado) => ['resultado' => $resultado],
            default => $resultado,
        };

        return ['functionResponse' => array_filter([
            'id' => $llamada['id'] ?? null,     // si el modelo lo envía, se devuelve igual
            'name' => $llamada['name'],
            'response' => $contenido,
        ], fn ($v) => $v !== null)];
    }

    /** Agrupa los resultados de todas las herramientas de una vuelta en un turno. */
    public static function turnoRespuestas(array $respuestas): array
    {
        return ['role' => 'user', 'parts' => $respuestas];
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    /** Bucle de modelos con presupuesto de tiempo (lógica anterior de generarJson). */
       /** Bucle de modelos con presupuesto de tiempo. */
    private function conRespaldo(array $modelos, Closure $intento, int $porModelo, int $total): mixed
    {
        $inicio = microtime(true);

        foreach ($modelos as $modelo) {
            $restante = $total - (microtime(true) - $inicio);

            // No se lanza un intento condenado al timeout: no llegaría y podría cobrarse igual.
            if ($restante < self::SEGUNDOS_MINIMOS) {
                Log::warning("Gemini: sin tiempo suficiente para probar {$modelo}", ['restante_s' => round($restante, 1)]);
                return null;
            }

            $resultado = $intento($modelo, (int) min($porModelo, floor($restante)));

            if ($resultado === self::PROBAR_OTRO) {
                continue;
            }

            return $resultado;
        }

        return null;
    }

    /** @return Response|string|null  Response = éxito; PROBAR_OTRO; null = error no recuperable */
    private function enviar(string $modelo, int $timeout, array $cuerpo): Response|string|null
    {
        try {
            $respuesta = Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                ->acceptJson()
                ->timeout($timeout)
                ->retry(2, 500, fn ($e) => $e instanceof RequestException
                    && in_array($e->response->status(), [429, 503]), throw: false)
                ->post(sprintf(self::URL, $modelo), $cuerpo);

            if ($respuesta->failed()) {
                 $nivel = $cuerpo['generationConfig']['thinkingConfig']['thinkingLevel'] ?? null;

                // El modelo no acepta ese nivel de razonamiento: se reintenta una vez
                // con el de respaldo y se recuerda para las próximas peticiones.
                if ($respuesta->status() === 400 && $nivel !== null && $nivel !== self::NIVEL_RESPALDO && str_contains($respuesta->body(), 'Thinking level')) {
                    Log::info("Gemini [{$modelo}]: no acepta thinkingLevel={$nivel}, se usa " . self::NIVEL_RESPALDO);
                    Cache::put("gemini:nivel:{$modelo}", self::NIVEL_RESPALDO, now()->addDays(7));

                    $cuerpo['generationConfig']['thinkingConfig']['thinkingLevel'] = self::NIVEL_RESPALDO;
                    return $this->enviar($modelo, $timeout, $cuerpo);
                }

                Log::warning("Gemini [{$modelo}]: respuesta con error", [
                    'estado' => $respuesta->status(),
                    'cuerpo' => mb_strimwidth($respuesta->body(), 0, 400),
                ]);

                return in_array($respuesta->status(), self::ESTADOS_PROBAR_OTRO) ? self::PROBAR_OTRO : null;
            }

            return $respuesta;
        } catch (\Throwable $e) {
            Log::warning("Gemini [{$modelo}]: no se pudo completar la consulta", ['error' => $e->getMessage()]);
            return self::PROBAR_OTRO;
        }
    }

       /** Nivel de respaldo para modelos que rechazan el configurado. */
    private const NIVEL_RESPALDO = 'low';

    /**
     * Razonamiento: solo Gemini 3.x acepta thinkingLevel (otros dan 400).
     * Si un modelo ya rechazó el nivel configurado, se usa el que aprendió.
     */
    private function razonamiento(string $modelo): array
    {
        if (! str_starts_with($modelo, 'gemini-3')) {
            return [];
        }

        $nivel = Cache::get("gemini:nivel:{$modelo}", config('services.gemini.thinking'));

        return $nivel ? ['thinkingConfig' => ['thinkingLevel' => $nivel]] : [];
    }

    /** Une las partes de texto, ignorando las de razonamiento. */
    private function textoDe(array $partes): string
    {
        return collect($partes)
            ->reject(fn ($parte) => $parte['thought'] ?? false)
            ->pluck('text')
            ->filter()
            ->implode('');
    }
}