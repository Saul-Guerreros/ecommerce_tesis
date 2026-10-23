<?php

namespace App\Services\Servicio;

use App\Mail\ServicioNotificacionMail;
use App\Models\Pedido\EstadoPago;
use App\Models\Servicio\EstadoPresupuesto;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\SolicitudServicio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * RF-033 - Notificaciones al cliente.
 *
 * Correo (automático):
 * - Se dispara desde ServicioEstadoService (un único punto: imposible
 *   olvidar avisar en un flujo nuevo).
 * - DB::afterCommit(): si la transacción falla, no se envía nada.
 * - Un error de SMTP NUNCA rompe la operación de negocio: se registra
 *   en el log y sigue.
 * - Cliente SIN cuenta (típico del mostrador): el botón invita a crear la cuenta
 *   con ese mismo correo (la cuenta se vincula sola, ver VinculacionCuentaService).
 * - Cliente sin correo: no se envía nada.
 *
 * WhatsApp (manual, decisión 1-a): enlaceWhatsapp() arma un enlace wa.me con el
 * mensaje del estado actual; el funcionario lo envía desde el WhatsApp de la sucursal.
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

    /** Avisos del eje de cobro (Paso 2 del pago del servicio). */
    private const AVISOS_PAGO = [
        EstadoPago::CONFIRMADO => 'Confirmamos tu pago',
        EstadoPago::RECHAZADO  => 'Revisá el comprobante de tu pago',
    ];

    public function alCambiarEstado(SolicitudServicio $s, string $estado, ?string $detalle): void
    {
        if (!isset(self::AVISOS[$estado])) {
            return;
        }

        $this->enviar($s, self::AVISOS[$estado], fn (bool $tieneCuenta) => $this->mensaje($estado, $s, $detalle, $tieneCuenta));
    }

    /**
     * "Pago confirmado" (todo el total cubierto) y "pago rechazado" (el
     * cliente tiene que subir otro comprobante). Mismo canal y mismas
     * garantías que alCambiarEstado: después del commit y sin romper nada.
     */
    public function alCambiarPago(SolicitudServicio $s, string $codigo, ?string $detalle): void
    {
        if (!isset(self::AVISOS_PAGO[$codigo])) {
            return;
        }

        $this->enviar($s, self::AVISOS_PAGO[$codigo], fn (bool $tieneCuenta) => $this->mensajePago($codigo, $s, $detalle, $tieneCuenta));
    }

    private function enviar(SolicitudServicio $s, string $asunto, callable $cuerpo): void
    {
        if (!config('servicio.notificar_por_correo')) {
            return;
        }

        DB::afterCommit(function () use ($s, $asunto, $cuerpo) {
            try {
                $correo = $this->correoDe($s);
                if (!$correo) {
                    return;
                }

                $tieneCuenta = $this->tieneCuenta($s);
                $tienda      = rtrim(config('servicio.url_tienda'), '/');

                Mail::to($correo)->send(new ServicioNotificacionMail(
                    numero: $s->sse_numero,
                    asunto: "{$asunto} — {$s->sse_numero}",
                    titulo: $asunto,
                    mensaje: $cuerpo($tieneCuenta),
                    url: $tieneCuenta
                        ? $tienda . '/mis-servicios/' . $s->sse_numero
                        : $tienda . '/register?email=' . rawurlencode($correo),
                    nombre: $s->cliente?->persona?->per_nombr1,
                    invitarRegistro: !$tieneCuenta,
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * Enlace wa.me con el mensaje del estado actual, o null si el cliente no tiene
     * un celular/WhatsApp válido. Prioriza el contacto WHATSAPP sobre CELULAR.
     */
    public function enlaceWhatsapp(SolicitudServicio $s): ?string
    {
        $s->loadMissing(['cliente.persona.contactos', 'cliente.persona.usuario', 'estadoServicio']);
        $contactos = $s->cliente?->persona?->contactos?->where('con_estado', 1) ?? collect();

        $numero = $this->numeroInternacional(
            $contactos->where('con_tipo', 'WHATSAPP')->first()?->con_valor
            ?? $contactos->where('con_tipo', 'CELULAR')->first()?->con_valor
        );
        if (!$numero) {
            return null;
        }

        $estado = $s->estadoServicio?->ets_codigo;
        $s->loadMissing('ordenServicio.presupuestoActual.estado');
        $pres   = $s->ordenServicio?->presupuestoActual;

        $s->loadMissing('estadoPago');
        $pago = $s->estadoPago?->epa_codigo;

        // Pago rechazado: es lo que el cliente tiene que resolver primero.
        if ($pago === EstadoPago::RECHAZADO) {
            $cuerpo = $this->mensajePago(EstadoPago::RECHAZADO, $s, null, $this->tieneCuenta($s));
        // Equipo listo con saldo: el mensaje lleva el monto a pagar.
        } elseif ($estado === EstadoServicio::LISTA && $pago === EstadoPago::PENDIENTE_PAGO) {
            $cuerpo = sprintf(
                'Tu equipo está listo. El total del servicio es de Gs. %s; podés pagarlo al retirarlo%s.',
                number_format((float) $s->sse_total, 0, ',', '.'),
                $this->tieneCuenta($s) ? ' o desde tu perfil en la tienda' : '',
            );
        // Paso 2: presupuesto esperando respuesta -> el mensaje lleva número, total y vencimiento.
        } elseif ($pres && $pres->codigoEstado() === EstadoPresupuesto::ENVIADO) {
            $cuerpo = sprintf(
                'Tu presupuesto %s es de Gs. %s y vence el %s. Respondenos por este medio si lo aprobás o lo rechazás%s.',
                $pres->pse_numero,
                number_format((float) $pres->pse_total, 0, ',', '.'),
                $pres->pse_fecven?->format('d/m/Y') ?? '—',
                $this->tieneCuenta($s) ? ', o hacelo desde tu perfil en la tienda' : '',
            );
        } else {
            $cuerpo = isset(self::AVISOS[$estado])
            ? $this->mensaje($estado, $s, null, $this->tieneCuenta($s))
            : 'Estado actual: ' . ($s->estadoServicio?->ets_descri ?? '—') . '.';
        }

        $nombre = $s->cliente?->persona?->per_nombr1;
        $texto  = 'Hola' . ($nombre ? " {$nombre}" : '') . ', te escribimos de Z Informática por tu servicio técnico '
                . "{$s->sse_numero}. {$cuerpo}";

        return 'https://wa.me/' . $numero . '?text=' . rawurlencode(trim($texto));
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

    private function tieneCuenta(SolicitudServicio $s): bool
    {
        return $s->cliente?->persona?->usuario !== null;
    }

    /**
     * Paraguay: 0981 123 456 -> 595981123456. Acepta números ya internacionales (595...).
     * Devuelve null si no parece un celular válido.
     */
    private function numeroInternacional(?string $valor): ?string
    {
        $d = preg_replace('/\D/', '', (string) $valor);

        if (str_starts_with($d, '595')) {
            $n = $d;
        } elseif (str_starts_with($d, '0')) {
            $n = '595' . substr($d, 1);
        } elseif (strlen($d) === 9 && str_starts_with($d, '9')) {
            $n = '595' . $d;
        } else {
            return null;
        }

        return strlen($n) >= 11 && strlen($n) <= 13 ? $n : null;
    }

    private function mensajePago(string $codigo, SolicitudServicio $s, ?string $detalle, bool $tieneCuenta): string
    {
        return match ($codigo) {
            EstadoPago::CONFIRMADO => $s->codigoServicio() === EstadoServicio::ENTREGADA
                ? 'Registramos el pago total de tu servicio. ¡Gracias!'
                : 'Registramos el pago total de tu servicio. Ya podés retirar tu equipo.',
            EstadoPago::RECHAZADO  => 'No pudimos validar el comprobante de tu pago'
                . ($detalle ? " ({$detalle})" : '') . '. '
                . ($tieneCuenta
                    ? 'Ingresá a tu perfil para cargar un comprobante nuevo.'
                    : 'Comunicate con la sucursal para regularizarlo.'),
            default => $detalle ?? '',
        };
    }

    private function mensaje(string $estado, SolicitudServicio $s, ?string $detalle, bool $tieneCuenta = true): string
    {
        return match ($estado) {
            EstadoServicio::RECIBIDA      => $s->esGarantiaDeCompra()
                ? 'Tu producto ya está en nuestro local. Un técnico va a gestionar la garantía con la empresa responsable y te avisamos de cada novedad.'
                : 'Tu equipo ya está en nuestro taller. Un técnico lo va a diagnosticar y te avisamos con el presupuesto.',
            EstadoServicio::EN_EMPRESA    => 'Enviamos tu producto a la empresa responsable de la garantía. Te avisamos apenas regrese al local.',
            EstadoServicio::PRESUPUESTADA => $tieneCuenta
                ? 'El técnico terminó el diagnóstico. Ingresá a tu perfil para aprobar o rechazar el presupuesto antes de que venza.'
                : 'El técnico terminó el diagnóstico. Comunicate con la sucursal para aprobar o rechazar el presupuesto antes de que venza.',
            EstadoServicio::LISTA         => 'Tu equipo está listo para retirar. ' . ($detalle ? "Detalle: {$detalle}." : ''),
            EstadoServicio::ENTREGADA     => $tieneCuenta
                ? 'Registramos la entrega de tu equipo. Podés descargar la constancia de retiro desde tu perfil.'
                : 'Registramos la entrega de tu equipo. Guardá la constancia de retiro que te entregamos en la sucursal.',
            EstadoServicio::CANCELADA     => $detalle ?: 'Tu solicitud fue cancelada.',
            default                       => $detalle ?? '',
        };
    }
}
