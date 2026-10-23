<?php

namespace App\Services\Servicio;

use App\Models\Pedido\EstadoPago;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\ServicioHistorialEstado;
use App\Models\Servicio\ServicioPago;
use App\Models\Servicio\SolicitudServicio;
use Illuminate\Validation\ValidationException;

/**
 * Estado agregado del cobro del servicio (solicitud_servicio.sse_epaid),
 * derivado SIEMPRE de servicio_pago. Copia el criterio de
 * EstadoPagoPedidoService (decisión 9-b: no se toca el pedido), con dos
 * diferencias propias del servicio:
 *
 *   1. Se compara por MONTOS y no por cantidad de pagos: un anticipo
 *      confirmado no cubre el total, así que "todos confirmados" no alcanza.
 *   2. Antes de LISTA el total no está consolidado: un anticipo solo puede
 *      dejar la solicitud en PARCIAL, nunca en CONFIRMADO.
 *
 * Montos "comprometidos" (no se pueden volver a cobrar):
 *   - CONFIRMADO y PAGO_VERIFICAR.
 *   - RECHAZADO con comprobante (transferencia/billetera): el cliente lo
 *     corrige subiendo otro comprobante para ESE pago.
 *   Un RECHAZADO sin comprobante (efectivo "pagar al retirar") queda libre:
 *   no hay nada que corregir, se cobra de nuevo.
 *
 * Debe llamarse dentro de la DB::transaction() del service que orquesta.
 */
class EstadoPagoServicioService
{
    public function __construct(private NotificadorServicio $notificador) {}

    /**
     * Recalcula sse_epaid y deja UNA fila de historial (tipo PAGO) por
     * operación, con el comentario de lo que pasó.
     *
     * @return bool true si la solicitud ACABA de quedar totalmente pagada
     */
    public function recalcular(SolicitudServicio $s, ?int $usuId, ?string $coment = null): bool
    {
        $anteriorId = $s->sse_epaid;
        $anterior   = $s->estadoPago()->value('epa_codigo');

        $codigo = $this->calcularCodigo($s);
        $nuevoId = $codigo ? EstadoPago::porCodigo($codigo)->epa_id : null;

        $s->sse_epaid = $nuevoId;
        $s->save();
        $s->load('estadoPago');

        if ($coment !== null || $nuevoId !== $anteriorId) {
            ServicioHistorialEstado::create([
                'she_sseid'  => $s->sse_id,
                'she_usuid'  => $usuId,
                'she_tipo'   => ServicioHistorialEstado::PAGO,
                'she_estant' => $anteriorId,
                'she_estid'  => $nuevoId,
                'she_coment' => $coment,
                'she_ip'     => request()->ip(),
            ]);
        }

        $pagado = $codigo === EstadoPago::CONFIRMADO && $anterior !== EstadoPago::CONFIRMADO;

        if ($pagado) {
            $this->notificador->alCambiarPago($s, EstadoPago::CONFIRMADO, null);
        }

        return $pagado;
    }

    /**
     * Resumen de montos. Lo usan los guards de cobro y el front (saldo a pagar).
     *
     * @return array{total: int, consolidado: bool, confirmado: int, en_verificacion: int,
     *               comprometido: int, saldo: int, admite_pagos: bool}
     */
    public function resumen(SolicitudServicio $s): array
    {
        $pagos = $s->pagos()->with(['estadoPago', 'metodoPago'])->get();

        $confirmado = $this->sumar($pagos, fn ($p) => $this->codigo($p) === EstadoPago::CONFIRMADO);
        $verificar  = $this->sumar($pagos, fn ($p) => $this->codigo($p) === EstadoPago::PAGO_VERIFICAR);
        $rechCorreg = $this->sumar($pagos, fn ($p) => $this->codigo($p) === EstadoPago::RECHAZADO
            && $p->metodoPago?->mpa_requcom);

        $total        = (int) round((float) $s->sse_total);
        $consolidado  = $this->consolidado($s);
        $comprometido = $confirmado + $verificar + $rechCorreg;

        return [
            'total'           => $total,
            'consolidado'     => $consolidado,
            'confirmado'      => $confirmado,
            'en_verificacion' => $verificar,
            'comprometido'    => $comprometido,
            'saldo'           => $consolidado ? max(0, $total - $comprometido) : 0,
            'admite_pagos'    => $this->admitePagos($s),
        ];
    }

    /**
     * Garantía (de compra o reclamo del trabajo): el cliente no paga nada
     * (regla 8). Lo mismo una solicitud cerrada.
     */
    public function admitePagos(SolicitudServicio $s): bool
    {
        if ($s->sse_garant) {
            return false;
        }

        $estado = $s->codigoServicio();

        if (in_array($estado, EstadoServicio::FINALES, true)) {
            return false;
        }

        // Ya consolidada: solo si hay algo que cobrar.
        return !$this->consolidado($s) || (float) $s->sse_total > 0;
    }

    /** Guard común: lanza 422 si la solicitud no admite pagos. */
    public function exigirQueAdmitaPagos(SolicitudServicio $s): void
    {
        if (!$this->admitePagos($s)) {
            throw ValidationException::withMessages([
                'pago' => $s->sse_garant
                    ? 'Este servicio está cubierto por garantía: no genera cobros.'
                    : 'Esta solicitud no tiene montos a cobrar.',
            ]);
        }
    }

    /**
     * ¿El total ya está fijado? Solo ServicioTotalesService::consolidar()
     * escribe sse_total (default 0), y lo hace ANTES de pasar a LISTA dentro
     * de la misma transacción. Por eso se mira también sse_total > 0: si se
     * mirara solo el estado, el recálculo que hace consolidar() vería la
     * solicitud todavía "sin total".
     */
    public function consolidado(SolicitudServicio $s): bool
    {
        return (float) $s->sse_total > 0
            || in_array($s->codigoServicio(), [EstadoServicio::LISTA, EstadoServicio::ENTREGADA], true);
    }

    // ---------- Cálculo ----------

    /**
     * Orden intencional (igual que en el pedido): un rechazo exige acción y
     * no puede quedar tapado por PARCIAL.
     *
     * NULL = no hay eje de cobro (total 0 consolidado y sin pagos, o todavía
     * sin pagos ni total).
     */
    private function calcularCodigo(SolicitudServicio $s): ?string
    {
        $r     = $this->resumen($s);
        $pagos = $s->pagos()->with(['estadoPago', 'metodoPago'])->get();

        if ($pagos->isEmpty()) {
            return $r['consolidado'] && $r['total'] > 0 ? EstadoPago::PENDIENTE_PAGO : null;
        }

        $hay = fn (string $c) => $pagos->contains(fn ($p) => $this->codigo($p) === $c);

        // Solo cuenta el rechazo que el cliente tiene que corregir (con comprobante).
        // Un efectivo rechazado no espera nada de nadie: se vuelve a cobrar.
        $rechazoPendiente = $pagos->contains(fn ($p) => $this->codigo($p) === EstadoPago::RECHAZADO
            && $p->metodoPago?->mpa_requcom);

        return match (true) {
            $r['consolidado'] && $r['total'] > 0 && $r['confirmado'] >= $r['total'] => EstadoPago::CONFIRMADO,
            $rechazoPendiente                => EstadoPago::RECHAZADO,
            $r['confirmado'] > 0             => EstadoPago::PARCIAL,
            $hay(EstadoPago::PAGO_VERIFICAR) => EstadoPago::PAGO_VERIFICAR,
            default                          => EstadoPago::PENDIENTE_PAGO,
        };
    }

    private function codigo(ServicioPago $p): ?string
    {
        return $p->estadoPago?->epa_codigo;
    }

    private function sumar($pagos, callable $filtro): int
    {
        return (int) round($pagos->filter($filtro)->sum(fn ($p) => (float) $p->spa_monto));
    }
}
