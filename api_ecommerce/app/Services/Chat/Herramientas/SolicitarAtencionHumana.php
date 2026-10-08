<?php

namespace App\Services\Chat\Herramientas;

use App\Models\Chat\{ChatConversacion, FaqTienda};
use App\Services\Chat\{ContextoChat, ResultadoHerramienta};

class SolicitarAtencionHumana implements HerramientaChat
{
    public function nombre(): string
    {
        return 'solicitar_atencion_humana';
    }

    public function declaracion(): array
    {
        return [
            'name' => $this->nombre(),
            'description' => 'Deriva la consulta a un vendedor cuando el cliente lo pide, está insatisfecho o '
                . 'pregunta algo que no se puede resolver con las otras herramientas. Devuelve los datos de contacto.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'motivo' => ['type' => 'string', 'description' => 'Resumen breve de lo que necesita el cliente'],
                ],
                'required' => ['motivo'],
            ],
        ];
    }

    public function ejecutar(array $args, ContextoChat $ctx): ResultadoHerramienta
    {
        $motivo = mb_substr(trim((string) ($args['motivo'] ?? '')), 0, 255) ?: 'Sin motivo indicado';
        $conversacion = $ctx->conversacion;

        // Idempotente: si ya estaba derivada, no se pisa el motivo original.
        if (! $conversacion->estaDerivada()) {
            $conversacion->update([
                'cnv_estado' => ChatConversacion::ESTADO_DERIVADA,
                'cnv_motivo_deriv' => $motivo,
            ]);
        }

        $contacto = FaqTienda::activas()
            ->whereIn('fqt_tema', ['contacto', 'horarios', 'retiro'])
            ->pluck('fqt_respuesta')
            ->all();

        return new ResultadoHerramienta(
            paraModelo: [
                'derivada' => true,
                'contacto' => $contacto,
                'instruccion' => 'Nadie de la tienda está leyendo este chat en este momento. '
                    . 'No digas que un vendedor lo va a atender, contactar ni responder. '
                    . 'Decile que, para hablar con una persona, debe comunicarse por estos medios.',
            ],
            motivoDerivacion: $motivo,
        );
    }
}