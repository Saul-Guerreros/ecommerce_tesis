<?php

namespace App\Services\Servicio;

use App\Mail\ServicioNotificacionMail;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\SolicitudServicio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * RF-033 - Notificaciones al cliente por correo.
 *
 * - Se disparan desde ServicioEstadoService (un único punto: imposible
 *   olvidar avisar en un flujo nuevo).
 * - DB::afterCommit(): si la transacción falla, no se envía nada.
 * - Un error de SMTP NUNCA rompe la operación de negocio: se registra
 *   en el log y sigue.
 */
class NotificadorServicio
{
    /** Estados que generan aviso, con el asunto del correo. */
    private const AVISOS = [
        EstadoServicio::RECIBIDA      => 'Recibimos tu equipo',
        EstadoServicio::EN_EMPRESA    => 'Enviamos tu producto a la empresa de la garantía',
        EstadoServicio::PRESUPUESTADA => 'Tenés un presupuesto para revisar',
        EstadoServicio::LISTA         => 'Tu equipo está listo',
        EstadoServicio::ENTREGADA     => 'Entregamos tu equipo',
        EstadoServicio::CANCELADA     => 'Tu solicitud fue cancelada',
    ];

    public function alCambiarEstado(SolicitudServicio $s, string $estado, ?string $detalle): void
    {
        if (!config('servicio.notificar_por_correo') || !isset(self::AVISOS[$estado])) {
            return;
        }

        $asunto = self::AVISOS[$estado];

        DB::afterCommit(function () use ($s, $estado, $asunto, $detalle) {
            try {
                $correo = $this->correoDe($s);
                if (!$correo) {
                    return;
                }

                Mail::to($correo)->send(new ServicioNotificacionMail(
                    numero: $s->sse_numero,
                    asunto: "{$asunto} — {$s->sse_numero}",
                    titulo: $asunto,
                    mensaje: $this->mensaje($estado, $s, $detalle),
                    url: rtrim(config('servicio.url_tienda'), '/') . '/mis-servicios/' . $s->sse_numero,
                    nombre: $s->cliente?->persona?->per_nombr1,
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /** El login del usuario es su correo; si no, el contacto EMAIL de la persona. */
    private function correoDe(SolicitudServicio $s): ?string
    {
        $persona = $s->cliente?->persona;
        $login   = $persona?->usuario?->usu_login;

        if ($login && filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return $login;
        }

        return $persona?->contactos()->where('con_tipo', 'EMAIL')->where('con_estado', 1)->value('con_valor');
    }

    private function mensaje(string $estado, SolicitudServicio $s, ?string $detalle): string
    {
        return match ($estado) {
            EstadoServicio::RECIBIDA      => $s->esGarantiaDeCompra()
                ? 'Tu producto ya está en nuestro local. Un técnico va a gestionar la garantía con la empresa responsable y te avisamos de cada novedad.'
                : 'Tu equipo ya está en nuestro taller. Un técnico lo va a diagnosticar y te avisamos con el presupuesto.',
            EstadoServicio::EN_EMPRESA    => 'Enviamos tu producto a la empresa responsable de la garantía. Te avisamos apenas regrese al local.',
            EstadoServicio::PRESUPUESTADA => 'El técnico terminó el diagnóstico. Ingresá a tu perfil para aprobar o rechazar el presupuesto antes de que venza.',
            EstadoServicio::LISTA         => 'Tu equipo está listo para retirar. ' . ($detalle ? "Detalle: {$detalle}." : ''),
            EstadoServicio::ENTREGADA     => 'Registramos la entrega de tu equipo. Podés descargar la constancia de retiro desde tu perfil.',
            EstadoServicio::CANCELADA     => $detalle ?: 'Tu solicitud fue cancelada.',
            default                       => $detalle ?? '',
        };
    }
}
