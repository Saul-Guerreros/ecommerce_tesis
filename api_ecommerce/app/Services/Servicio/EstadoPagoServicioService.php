<?php

namespace App\Services\Servicio;

use App\Models\Pedido\EstadoPago;
use App\Models\Servicio\EstadoPresupuesto;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\PresupuestoServicio;
use App\Models\Servicio\ServicioHistorialEstado;
use App\Models\Servicio\ServicioPago;
use App\Models\Servicio\SolicitudServicio;
use Illuminate\Validation\ValidationException;

/**
 * Estado agregado del cobro del servicio (sse_epaid), derivado SIEMPRE de
 * servicio_pago. Se compara por MONTOS: el anticipo cobrado + el saldo
 * cobrado tienen que cubrir el total.
 *
 * Ciclo (igual que pedidos): comprobante -> factura -> cobro. Este service
 * YA NO EMITE facturas: las genera el usuario (FacturaServicioService) y
 * un pago queda CONFIRMADO recién cuando se registra su cobro.
 *
 * Montos "comprometidos" (no se vuelven a pedir):
 *   - CONFIRMADO y PAGO_VERIFICAR.
 *   - RECHAZADO con comprobante: el cliente lo corrige subiendo otro.
 *
 * Debe llamarse dentro de la DB::transaction() del service que orquesta.
 */
class EstadoPagoServicioService
{
    /** Equipo recibido y todavía no listo: se admite UN anticipo. */
    public const ESTADOS_ANTICIPO = [
        EstadoServicio::RECIBIDA,
        EstadoServicio::EN_DIAGNOSTICO,
        EstadoServicio::PRESUPUESTADA,
        EstadoServicio::APROBADA,
        EstadoServicio::EN_REPARACION,
    ];

    public function __construct(private NotificadorServicio $notificador) {}

    /**
     * Recalcula sse_epaid y deja una fila de historial (tipo PAGO).
     *
     * @return bool true si la solicitud ACABA de quedar totalmente cobrada
     */
    public function recalcular(SolicitudServicio $s, ?int $usuId, ?string $coment = null): bool
    {
        $anteriorId = $s->sse_epaid;
        $anterior   = $s->estadoPago()->value('epa_codigo');

        $codigo  = $this->calcularCodigo($s);
        $nuevoId = $codigo ? EstadoPago::porCodigo($codigo)->epa_id : null;

        $s->sse_epaid = $nuevoId;
        $s->save();
        $s->load('estadoPago');

        $pagado = $codigo === EstadoPago::CONFIRMADO && $anterior !== EstadoPago::CONFIRMADO;

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

        if ($pagado) {
            $this->notificador->alCambiarPago($s, EstadoPago::CONFIRMADO, null);
        }

        return $pagado;
    }

    /**
     * Resumen de montos. Lo usan los guards de cobro y el front.
     *
     * @return array{total: int, consolidado: bool, anticipo: int, confirmado: int,
     *               en_verificacion: int, comprometido: int, saldo: int, admite_pagos: bool}
     */
    public function resumen(SolicitudServicio $s): array
    {
        $pagos = $s->pagos()->with(['estadoPago', 'metodoPago'])->get();

        $confirmado = $this->sumar($pagos, fn ($p) => $this->codigo($p) === EstadoPago::CONFIRMADO);
        $verificar  = $this->sumar($pagos, fn ($p) => $this->codigo($p) === EstadoPago::PAGO_VERIFICAR);
        $rechCorreg = $this->sumar($pagos, fn ($p) => $this->codigo($p) === EstadoPago::RECHAZADO
            && $p->metodoPago?->mpa_requcom);
        $anticipo   = $this->sumar($pagos, fn ($p) => $p->esAnticipo()
            && $this->codigo($p) !== EstadoPago::RECHAZADO);

        $total        = (int) round((float) $s->sse_total);
        $consolidado  = $this->consolidado($s);
        $comprometido = $confirmado + $verificar + $rechCorreg;

        return [
            'total'           => $total,
            'consolidado'     => $consolidado,
            'anticipo'        => $anticipo,
            'confirmado'      => $confirmado,
            'en_verificacion' => $verificar,
            'comprometido'    => $comprometido,
            'saldo'           => $consolidado ? max(0, $total - $comprometido) : 0,
            'admite_pagos'    => $this->admitePagos($s),
        ];
    }

    /** Garantía o solicitud cerrada: el cliente no paga nada (regla 8). */
    public function admitePagos(SolicitudServicio $s): bool
    {
        if ($s->sse_garant) {
            return false;
        }

        if (in_array($s->codigoServicio(), EstadoServicio::FINALES, true)) {
            return false;
        }

        return !$this->consolidado($s) || (float) $s->sse_total > 0;
    }

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
     * La API admite UN anticipo por factura final (guía 29): un anticipo
     * declarado o facturado impide otro. Uno RECHAZADO libera el lugar.
     */
    public function exigirSinAnticipo(SolicitudServicio $s): void
    {
        if ($s->facturaAnticipo()->exists() || $s->anticipoSinResolver() > 0) {
            throw ValidationException::withMessages([
                'pago' => 'Esta solicitud ya tiene un anticipo. Se admite un solo anticipo por servicio.',
            ]);
        }
    }

    /**
     * Máximo del anticipo. NULL = sin tope (todavía no hay presupuesto
     * aprobado). Los complementarios (v2+) traen solo lo adicional: se suman.
     */
    public function topeAnticipo(SolicitudServicio $s): ?int
    {
        $aprobado = (int) round((float) PresupuestoServicio::query()
            ->where('pse_oseid', $s->ordenServicio?->ose_id ?? 0)
            ->whereHas('estado', fn ($q) => $q->where('epr_codigo', EstadoPresupuesto::APROBADO))
            ->sum('pse_total'));

        if ($aprobado <= 0) {
            return null;
        }

        return $aprobado + (int) round((float) $s->sse_cosret);
    }

        /** Tienda: ¿el cliente puede pagar un anticipo ahora? Y con qué tope. */
    public function anticipoWeb(SolicitudServicio $s): array
    {
        $permitido = $this->admitePagos($s)
            && in_array($s->codigoServicio(), self::ESTADOS_ANTICIPO, true)
            && !$s->facturaAnticipo()->exists()
            && $s->anticipoSinResolver() <= 0;

        return [
            'permitido' => $permitido,
            'tope'      => $permitido ? $this->topeAnticipo($s) : null,
        ];
    }

    /**
     * ¿El total ya está fijado? Solo ServicioTotalesService::consolidar()
     * escribe sse_total, antes de pasar a LISTA y en la misma transacción.
     */
    public function consolidado(SolicitudServicio $s): bool
    {
        return (float) $s->sse_total > 0
            || in_array($s->codigoServicio(), [EstadoServicio::LISTA, EstadoServicio::ENTREGADA], true);
    }

    // ---------- Cálculo ----------

    private function calcularCodigo(SolicitudServicio $s): ?string
    {
        $r     = $this->resumen($s);
        $pagos = $s->pagos()->with(['estadoPago', 'metodoPago'])->get();

        if ($pagos->isEmpty()) {
            return $r['consolidado'] && $r['total'] > 0 ? EstadoPago::PENDIENTE_PAGO : null;
        }

        $hay = fn (string $c) => $pagos->contains(fn ($p) => $this->codigo($p) === $c);

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