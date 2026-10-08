<?php

namespace App\Services\Chat;

use App\Models\Chat\ChatConversacion;
use App\Models\Chat\ChatMensaje;
use App\Services\Chat\Herramientas\RegistroHerramientas;
use App\Services\IA\GeminiClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MÓDULO 6 - ChatBot (Facade). Resuelve un mensaje del cliente:
 * conversación → historial → bucle de function calling → persistencia.
 * Nunca lanza excepciones al cliente: si la IA falla, responde un mensaje
 * de respaldo y deja registrado el turno.
 */
class ChatbotService
{
    /** Vueltas por turno; la última obliga a responder con texto. */
    private const MAX_VUELTAS = 4;
    /** Mensajes previos (usuario + asistente) que se envían como contexto. */
    private const HISTORIAL = 10;
    /** Pasado este tiempo, la siguiente vuelta es la última. */
    private const SEGUNDOS_PARA_NUEVA_VUELTA = 30;
    /** Pasado este tiempo ya no se intenta el rescate (límite del servidor: 60 s). */
    private const SEGUNDOS_PARA_RESCATE = 42;

    public function __construct(
        private GeminiClient $gemini,
        private RegistroHerramientas $herramientas,
        private InstruccionesChat $instrucciones,
    ) {
    }

    public function responder(string $mensaje, ?string $token, ?string $ip, ?int $usuId = null): RespuestaChat
    {
        $inicio = microtime(true);
        $conversacion = $this->obtenerConversacion($token, $ip, $usuId);
        $ctx = new ContextoChat($conversacion, $ip, $conversacion->usu_id);

        // El historial se lee ANTES de guardar el mensaje nuevo, que se agrega al final.
        $historial = $this->historial($conversacion);
        $contenidos = [...$historial, GeminiClient::turnoUsuario($mensaje)];
        $this->guardar($conversacion, ChatMensaje::ROL_USUARIO, ['cms_contenido' => $mensaje]);

        $acumulador = new AcumuladorTurno();
        $instrucciones = $this->instrucciones->texto();
        $declaraciones = $this->herramientas->declaraciones();
        $texto = null;

        for ($vuelta = 1; $vuelta <= self::MAX_VUELTAS; $vuelta++) {
            $ultima = $vuelta === self::MAX_VUELTAS
                || (microtime(true) - $inicio) > self::SEGUNDOS_PARA_NUEVA_VUELTA;

            $respuesta = $this->gemini->conversar($instrucciones, $contenidos, $declaraciones,
                $acumulador->modelo, permitirHerramientas: ! $ultima);

            if ($respuesta === null) {
                // Falló el modelo fijo a mitad del turno: se rescata con cualquier modelo.
                if ($acumulador->vueltas > 0 && (microtime(true) - $inicio) < self::SEGUNDOS_PARA_RESCATE) {
                    $texto = $this->rescatar($instrucciones, $historial, $mensaje, $acumulador);
                }
                break;
            }

            $acumulador->registrarRespuestaModelo($respuesta);

            if ($respuesta['llamadas'] === [] || $ultima) {
                $texto = trim($respuesta['texto']);
                break;
            }

            $contenidos[] = GeminiClient::turnoModelo($respuesta['partes']);
            $contenidos[] = GeminiClient::turnoRespuestas(
                $this->ejecutarHerramientas($respuesta['llamadas'], $ctx, $acumulador));
        }

        $respaldo = ! $texto;
        $texto = $texto ?: $this->textoRespaldo($acumulador);

        $this->guardar($conversacion, ChatMensaje::ROL_ASISTENTE, [
            'cms_contenido' => $texto,
            'cms_modelo' => $acumulador->modelo,
            'cms_tokens_ent' => $acumulador->tokensEntrada ?: null,
            'cms_tokens_sal' => $acumulador->tokensSalida ?: null,
            'cms_latencia_ms' => (int) round((microtime(true) - $inicio) * 1000),
            'cms_datos' => array_filter([
                'productos' => $acumulador->productosMostrados(),
                'acciones' => $acumulador->acciones(),
                'vueltas' => $acumulador->vueltas,
                'respaldo' => $respaldo ?: null,
                'rescate' => $acumulador->rescatado ?: null,
            ]),
        ]);

        $conversacion->update(['cnv_ultimo_msj' => now()]);

        return new RespuestaChat(
            token: $conversacion->cnv_token,
            texto: $texto,
            tarjetas: $acumulador->tarjetas(),
            acciones: $acumulador->acciones(),
            derivada: $conversacion->estaDerivada(),
        );
    }

    /**
     * Mensajes para redibujar el chat al recargar la página (solo texto:
     * las tarjetas no se reconstruyen). null = no existe o no es de quien pregunta.
     */
    public function mensajesVisibles(string $token, ?int $usuId): ?array
    {
        $conversacion = ChatConversacion::where('cnv_token', $token)->first();

        if (! $conversacion || ! $this->perteneceA($conversacion, $usuId)) {
            return null;
        }

        return $conversacion->mensajes()
            ->whereIn('cms_rol', [ChatMensaje::ROL_USUARIO, ChatMensaje::ROL_ASISTENTE])
            ->get(['cms_rol', 'cms_contenido', 'cms_creado'])
            ->map(fn (ChatMensaje $m) => [
                'rol' => $m->cms_rol,
                'texto' => $m->cms_contenido,
                'fecha' => $m->cms_creado?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Reutiliza la conversación del token solo si sigue abierta y pertenece a
     * quien pregunta. Si no, crea una nueva: un token ajeno o el de un cliente
     * que cerró sesión nunca da acceso a una conversación con dueño.
     */
    private function obtenerConversacion(?string $token, ?string $ip, ?int $usuId): ChatConversacion
    {
        $conversacion = $token ? ChatConversacion::where('cnv_token', $token)->first() : null;

        $reutilizable = $conversacion
            && $conversacion->cnv_estado !== ChatConversacion::ESTADO_CERRADA
            && $this->perteneceA($conversacion, $usuId);

        if (! $reutilizable) {
            return ChatConversacion::create([
                'cnv_token' => (string) Str::uuid(),
                'usu_id' => $usuId,
                'cnv_ip' => $ip,
            ]);
        }

        // Un invitado que inicia sesión: la conversación pasa a ser suya.
        if ($usuId && $conversacion->usu_id === null) {
            $conversacion->update(['usu_id' => $usuId]);
        }

        return $conversacion;
    }

    /** Conversación de invitado: basta el token. Con dueño: solo ese usuario. */
    private function perteneceA(ChatConversacion $conversacion, ?int $usuId): bool
    {
        return $conversacion->usu_id === null || $conversacion->usu_id === $usuId;
    }

    /** Últimos mensajes en formato Gemini, con referencia a los productos mostrados. */
    private function historial(ChatConversacion $conversacion): array
    {
        $contenidos = ChatMensaje::where('cnv_id', $conversacion->cnv_id)
            ->whereIn('cms_rol', [ChatMensaje::ROL_USUARIO, ChatMensaje::ROL_ASISTENTE])
            ->whereNotNull('cms_contenido')
            ->orderByDesc('cms_id')
            ->limit(self::HISTORIAL)
            ->get()
            ->reverse()
            ->map(fn (ChatMensaje $m) => $m->cms_rol === ChatMensaje::ROL_USUARIO
                ? GeminiClient::turnoUsuario($m->cms_contenido)
                : GeminiClient::turnoAsistente($this->conReferencias($m)))
            ->values()
            ->all();

        // La API espera que la conversación empiece con un turno del usuario.
        while ($contenidos !== [] && $contenidos[0]['role'] === 'model') {
            array_shift($contenidos);
        }

        return $contenidos;
    }

    /** "agregá la segunda": el modelo necesita saber qué ids mostró antes. */
    private function conReferencias(ChatMensaje $mensaje): string
    {
        $productos = $mensaje->cms_datos['productos'] ?? [];

        if ($productos === []) {
            return $mensaje->cms_contenido;
        }

        $lista = collect($productos)->map(fn ($p) => "id {$p['id']}: {$p['titulo']}")->implode(' | ');

        return $mensaje->cms_contenido . "\n\n[Contexto interno, no mostrar: productos mostrados → {$lista}]";
    }

    private function ejecutarHerramientas(array $llamadas, ContextoChat $ctx, AcumuladorTurno $acumulador): array
    {
        $respuestas = [];

        foreach ($llamadas as $llamada) {
            $args = $llamada['args'] ?? [];
            $resultado = $this->herramientas->ejecutar($llamada['name'], $args, $ctx);
            $acumulador->registrarHerramienta($llamada['name'], $resultado);

            // Auditoría: qué pidió el modelo y qué se obtuvo (no el resultado completo).
            $this->guardar($ctx->conversacion, ChatMensaje::ROL_HERRAMIENTA, [
                'cms_herramienta' => $llamada['name'],
                'cms_datos' => array_filter([
                    'args' => $args,
                    'pub_ids' => array_column($resultado->tarjetas, 'pub_id'),
                    'acciones' => $resultado->acciones,
                    'error' => $resultado->paraModelo['error'] ?? null,
                ]),
            ]);

            $respuestas[] = GeminiClient::respuestaHerramienta($llamada, $resultado->paraModelo);
        }

        return $respuestas;
    }

    private function textoRespaldo(AcumuladorTurno $acumulador): string
    {
        return $acumulador->tarjetas() !== []
            ? 'Tuve un problema para terminar mi respuesta, pero encontré estos productos que pueden interesarte.'
            : 'Perdón, en este momento no puedo responder. Probá de nuevo en unos minutos o usá el buscador de la tienda.';
    }

    /**
     * El modelo fijo falló después de usar herramientas. Se reintenta con la
     * cadena de respaldo completa, enviando los resultados como TEXTO: sin
     * partes de function calling no hacen falta firmas de razonamiento.
     */
    private function rescatar(string $instrucciones, array $historial, string $mensaje, AcumuladorTurno $acumulador): ?string
    {
        $datos = json_encode($acumulador->resultados, JSON_UNESCAPED_UNICODE);

        $contenidos = [...$historial, GeminiClient::turnoUsuario(
            $mensaje . "\n\n[Contexto interno, no mostrar: datos ya consultados en la tienda para "
            . "responder este mensaje → {$datos}. Respondé usando solo estos datos.]"
        )];

        $respuesta = $this->gemini->conversar($instrucciones, $contenidos);   // sin herramientas

        if ($respuesta === null || trim($respuesta['texto']) === '') {
            return null;
        }

        $acumulador->registrarRespuestaModelo($respuesta);
        $acumulador->rescatado = true;
        Log::info("Chat: respuesta rescatada con {$respuesta['modelo']}");

        return trim($respuesta['texto']);
    }

    private function guardar(ChatConversacion $conversacion, string $rol, array $datos): void
    {
        $conversacion->mensajes()->create(['cms_rol' => $rol] + $datos);
    }
}