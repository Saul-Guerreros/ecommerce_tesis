<?php

namespace App\Console\Commands;

use App\Services\Chat\ChatbotService;
use Illuminate\Console\Command;

/** Conversación interactiva con el ChatBot completo (guarda en la base). */
class ConversarChat extends Command
{
    protected $signature = 'chat:conversar';
    protected $description = 'Conversa con el ChatBot desde la consola (escribí "salir" para terminar)';

    public function handle(ChatbotService $chatbot): int
    {
        $token = null;
        $this->info('Escribí "salir" para terminar.');

        while (true) {
            $mensaje = trim((string) $this->ask('Vos'));

            if ($mensaje === '') {
                continue;
            }
            if (in_array(mb_strtolower($mensaje), ['salir', 'exit'], true)) {
                break;
            }

            $inicio = microtime(true);
            $respuesta = $chatbot->responder($mensaje, $token, '127.0.0.1');
            $token = $respuesta->token;

            $this->line(sprintf('<fg=cyan>Bot</> (%d ms): %s',
                (microtime(true) - $inicio) * 1000, $respuesta->texto));

            foreach ($respuesta->tarjetas as $t) {
                $this->line('   [tarjeta] ' . $t['titulo'] . ' · Gs. ' . number_format($t['precio_final'], 0, ',', '.'));
            }
            foreach ($respuesta->acciones as $a) {
                $this->line("   [botón] Agregar {$a['cantidad']} × {$a['titulo']}");
            }
            if ($respuesta->derivada) {
                $this->line('   [conversación derivada]');
            }
        }

        $this->info("Token de la conversación: {$token}");
        return self::SUCCESS;
    }
}