<?php

namespace App\Services\Servicio;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\MotivoRechazoPago;
use App\Models\Pedido\PagoCheque;
use App\Models\Servicio\EstadoServicio;
use App\Models\Servicio\ServicioCheque;
use App\Models\Servicio\ServicioChequeCambio;
use App\Models\Servicio\ServicioHistorialEstado;
use App\Models\Servicio\ServicioPago;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
/**
 * Ciclo del cheque físico del servicio. Espejo de ChequeGestionService con
 * dos diferencias: recibir pasa el pago a PAGO_VERIFICAR (sigue el camino
 * factura -> cobro), y rechazar NO cancela la solicitud (reabre el saldo).
 */
class ChequeServicioGestionService
{
    public function __construct(
        private EstadoPagoServicioService $estadoPago,
        private NotificadorServicio $notificador,
    ) {}

    /** [Editar]. Un DESCARTADO vuelve a EN_ESPERA al cargar los datos correctos. */
    public function actualizarDatos(SolicitudServicio $s, int $pagoId, array $datos, Usuario $usuario): void
    {
        DB::transaction(function () use ($s, $pagoId, $datos, $usuario) {
            [$s, $cheque] = $this->bloquear($s, $pagoId);

            if (!$cheque->estaPendiente()) {
                throw ValidationException::withMessages([
                    'cheque' => "Un cheque en estado \"{$cheque->descripcionEstado()}\" ya no se edita.",
                ]);
            }

            ServicioChequeCambio::registrar($cheque, ServicioChequeCambio::EDICION, $usuario->usu_id);

            $cheque->update([
                'sch_tipo'   => $datos['tipo'],
                'sch_banco'  => $datos['banco'],
                'sch_nombre' => $datos['nombre'],
                'sch_nrodoc' => $datos['nrodoc'],
                'sch_nro'    => $datos['nro'],
                'sch_fecobr' => $datos['tipo'] === PagoCheque::TIPO_DIFERIDO ? ($datos['fecha_cobro'] ?? null) : null,
                'sch_estado' => PagoCheque::EN_ESPERA,
            ]);

            $this->historial($s, $usuario->usu_id,
                "Datos del cheque del pago {$cheque->pago->spa_orden} corregidos por el administrador");
        });
    }

    /** [Descartar cheque]: el papel presentado es otro. No toca el plazo. */
    public function descartar(SolicitudServicio $s, int $pagoId, array $datos, Usuario $usuario): void
    {
        DB::transaction(function () use ($s, $pagoId, $datos, $usuario) {
            [$s, $cheque] = $this->bloquear($s, $pagoId);

            if ($cheque->sch_estado !== PagoCheque::EN_ESPERA) {
                throw ValidationException::withMessages([
                    'cheque' => 'Sólo se puede descartar un cheque que todavía está en espera.',
                ]);
            }

            ServicioChequeCambio::registrar($cheque, ServicioChequeCambio::DESCARTE, $usuario->usu_id, $datos['motivo'] ?? null);

            $cheque->update([
                'sch_banco' => null, 'sch_nombre' => null, 'sch_nrodoc' => null,
                'sch_nro'   => null, 'sch_fecobr' => null,
                'sch_estado' => PagoCheque::DESCARTADO,
            ]);

            $this->historial($s, $usuario->usu_id,
                "Cheque del pago {$cheque->pago->spa_orden} descartado"
                . (blank($datos['motivo'] ?? null) ? '' : " — {$datos['motivo']}"));
        });
    }

    /** [Confirmar recepción]: el pago pasa a PAGO_VERIFICAR (ya se puede facturar). */
    public function confirmarRecepcion(SolicitudServicio $s, int $pagoId, array $datos, Usuario $usuario): void
    {
        DB::transaction(function () use ($s, $pagoId, $datos, $usuario) {
            [$s, $cheque] = $this->bloquear($s, $pagoId);

            if ($cheque->fueRecibido()) {
                throw ValidationException::withMessages(['cheque' => 'Este cheque ya figura como recibido.']);
            }

            if (!$cheque->estaPendiente()) {
                throw ValidationException::withMessages([
                    'cheque' => "Un cheque en estado \"{$cheque->descripcionEstado()}\" ya no se puede recibir.",
                ]);
            }

            if (!$cheque->datosCompletos()) {
                throw ValidationException::withMessages([
                    'cheque' => 'Faltan datos del cheque. Cargá banco, titular, CI, número y '
                        . '—si es diferido— la fecha de cobro antes de confirmar la recepción.',
                ]);
            }

            $cheque->update([
                'sch_estado' => PagoCheque::RECIBIDO,
                'sch_frecep' => now(),
                'sch_usurec' => $usuario->usu_id,
                'sch_motdet' => $datos['observacion'] ?? null,
            ]);

            $cheque->pago->update(['spa_epaid' => EstadoPago::porCodigo(EstadoPago::PAGO_VERIFICAR)->epa_id]);

            $this->historial($s, $usuario->usu_id, sprintf(
                'Cheque %s del pago %d recibido — Gs. %s',
                $cheque->sch_nro, $cheque->pago->spa_orden, $this->gs((float) $cheque->pago->spa_monto),
            ));

            $this->estadoPago->recalcular($s, $usuario->usu_id);
        });
    }

        /**
     * [Rechazar cheque]: igual que en pedidos, solo mientras el papel NO
     * fue recibido (en espera o descartado). Un cheque recibido ya no se
     * rechaza: si rebota, se anula el cobro o corresponde nota de crédito.
     *
     * Única diferencia con pedidos (decisión de negocio): no cancela la
     * solicitud. Rechaza ese pago y el saldo vuelve a quedar disponible.
     *
     * @param array{motivo_id:int, detalle:?string} $datos
     */
    public function rechazar(SolicitudServicio $s, int $pagoId, array $datos, Usuario $usuario): void
    {
        DB::transaction(function () use ($s, $pagoId, $datos, $usuario) {
            [$s, $cheque] = $this->bloquear($s, $pagoId);

            $this->validarRechazable($cheque);

            $motivo = MotivoRechazoPago::activos()->findOrFail($datos['motivo_id']);

            if (!$motivo->aplicaA(MotivoRechazoPago::AMBITO_CHEQUE)) {
                throw ValidationException::withMessages(['motivo_id' => 'Ese motivo no corresponde al rechazo de un cheque.']);
            }

            if ($motivo->mrp_requdet && blank($datos['detalle'] ?? null)) {
                throw ValidationException::withMessages(['detalle' => 'Este motivo exige un detalle para el cliente.']);
            }

            $this->cerrarSinCobro($s, $cheque, PagoCheque::RECHAZADO, $motivo, $datos['detalle'] ?? null, $usuario->usu_id);
        });
    }

    private function validarRechazable(ServicioCheque $cheque): void
    {
        if ($cheque->fueRecibido()) {
            throw ValidationException::withMessages([
                'cheque' => 'Este cheque ya fue recibido. Si rebotó después, corresponde '
                    . 'anular el cobro o emitir una nota de crédito, no rechazar la recepción.',
            ]);
        }

        if (!$cheque->estaPendiente()) {
            throw ValidationException::withMessages([
                'cheque' => "Un cheque en estado \"{$cheque->descripcionEstado()}\" ya no se rechaza.",
            ]);
        }
    }

    /** Barrido automático (48 h). Revalida con lock. */
    public function marcarNoEntregado(ServicioCheque $cheque): bool
    {
        return DB::transaction(function () use ($cheque) {
            $cheque = ServicioCheque::whereKey($cheque->sch_id)->lockForUpdate()->with('pago')->first();

            if (!$cheque || !$cheque->vencido()) {
                return false;
            }

            $s = SolicitudServicio::whereKey($cheque->pago->spa_sseid)->lockForUpdate()->with('estadoServicio')->firstOrFail();

            if ($s->codigoServicio() === EstadoServicio::CANCELADA) {
                return false;
            }

            $motivo = MotivoRechazoPago::where('mrp_codigo', MotivoRechazoPago::CHQ_FUERA_DE_PLAZO)->firstOrFail();

            $this->cerrarSinCobro($s, $cheque, PagoCheque::NO_ENTREGADO, $motivo, null, null);

            return true;
        });
    }

    /**
     * Cheque RECHAZADO / NO_ENTREGADO -> pago RECHAZADO. Como el método no
     * lleva comprobante, el pago no es "corregible": deja de contar como
     * comprometido y el saldo se reabre.
     */
    private function cerrarSinCobro(SolicitudServicio $s, ServicioCheque $cheque, string $estado,
        MotivoRechazoPago $motivo, ?string $detalle, ?int $usuarioId): void
    {
        $texto = $detalle ?: $motivo->mrp_descri;

        $cheque->update([
            'sch_estado' => $estado,
            'sch_mrpid'  => $motivo->mrp_id,
            'sch_motdet' => $texto,
            'sch_fevcto' => null,
        ]);

        $cheque->pago->update([
            'spa_epaid'   => EstadoPago::porCodigo(EstadoPago::RECHAZADO)->epa_id,
            'spa_mrpid'   => $motivo->mrp_id,
            'spa_observ'  => $texto,
            'spa_usuver'  => $usuarioId,
            'spa_fechver' => now(),
        ]);

        $accion = $estado === PagoCheque::NO_ENTREGADO ? 'no entregado en plazo' : 'rechazado';

        $this->historial($s, $usuarioId, "Cheque del pago {$cheque->pago->spa_orden} {$accion} — {$motivo->mrp_descri}");

        $this->estadoPago->recalcular($s, $usuarioId, "Pago {$cheque->pago->spa_orden} rechazado — {$motivo->mrp_descri}");

        if ($cheque->pago->spa_canal === ServicioPago::CANAL_WEB) {
            $this->notificador->alCambiarPago($s, EstadoPago::RECHAZADO, $texto);
        }
    }

    /** @return array{0: SolicitudServicio, 1: ServicioCheque} */
    private function bloquear(SolicitudServicio $s, int $pagoId): array
    {
        $s = SolicitudServicio::whereKey($s->sse_id)->lockForUpdate()->with('estadoServicio')->firstOrFail();

        if ($s->codigoServicio() === EstadoServicio::CANCELADA) {
            throw ValidationException::withMessages(['cheque' => 'La solicitud está cancelada: sus cheques ya no se gestionan.']);
        }

        $pago = ServicioPago::where('spa_id', $pagoId)->where('spa_sseid', $s->sse_id)->first();
        abort_if(!$pago, 404, 'El pago no pertenece a esta solicitud.');

        $cheque = ServicioCheque::where('sch_spaid', $pagoId)->lockForUpdate()->first();
        abort_if(!$cheque, 404, 'Este pago no se realizó con cheque.');

        $cheque->setRelation('pago', $pago);

        return [$s, $cheque];
    }

    private function historial(SolicitudServicio $s, ?int $usuarioId, string $comentario): void
    {
        ServicioHistorialEstado::create([
            'she_sseid'  => $s->sse_id,
            'she_usuid'  => $usuarioId,
            'she_tipo'   => ServicioHistorialEstado::CHEQUE,
            'she_estant' => $s->sse_epaid,
            'she_estid'  => $s->sse_epaid,
            'she_coment' => $comentario,
            'she_ip'     => request()->ip(),
        ]);
    }

    private function gs(int|float $monto): string
    {
        return number_format($monto, 0, ',', '.');
    }
}