<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ChatMensajeRequest;
use App\Services\Chat\ChatbotService;
use Illuminate\Http\JsonResponse;

/**
 * MÓDULO 6 - ChatBot. Rutas públicas: si llega un JWT válido de cliente,
 * la conversación se vincula a su usuario; si no, funciona como invitado.
 * Toda la lógica vive en ChatbotService.
 */
class ChatController extends Controller
{
    public function __construct(private ChatbotService $chatbot)
    {
    }

    // POST /api/tienda/chat/mensajes
    public function enviar(ChatMensajeRequest $request): JsonResponse
    {
        set_time_limit(70);   // el turno se autolimita (~60 s en el peor caso); esto evita que PHP lo corte antes
        $respuesta = $this->chatbot->responder(
            mensaje: $request->validated('mensaje'),
            token: $request->validated('token'),
            ip: $request->ip(),
            usuId: $this->usuarioId(),
        );

        return response()->json(['data' => $respuesta->toArray()]);
    }

    // GET /api/tienda/chat/{token}
    public function historial(string $token): JsonResponse
    {
        $mensajes = $this->chatbot->mensajesVisibles($token, $this->usuarioId());

        abort_if($mensajes === null, 404, 'Conversación no encontrada.');

        return response()->json(['data' => ['token' => $token, 'mensajes' => $mensajes]]);
    }

    /**
     * Ruta pública: el guard intenta leer el JWT del header. Si no hay token,
     * es inválido o expiró, devuelve null y se atiende como invitado.
     */
    private function usuarioId(): ?int
    {
        $id = auth('api')->id();

        return $id !== null ? (int) $id : null;
    }
}