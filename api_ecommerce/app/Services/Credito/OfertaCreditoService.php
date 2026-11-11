<?php

namespace App\Services\Credito;

use App\Models\Credito\EstadoSolicitud;
use App\Models\Credito\SolicitudCredito;
use App\Models\Credito\SolicitudCreditoHistorial;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Respuesta del CLIENTE a la oferta de crédito aprobada (etapa D).
 *
 * Tercer ciclo de vida sobre la misma entidad, separado a propósito:
 *   - SolicitudService          → generación desde el carrito (cliente).
 *   - GestionSolicitudService   → evaluación y decisión (admin).
 *   - OfertaCreditoService      → aceptar / rechazar la oferta (cliente).
 *
 * Reglas:
 *   - Solo se responde una oferta APROBADA y sin aceptar.
 *   - ACEPTAR exige oferta vigente (hasta el final del día de scr_vighas).
 *     La reserva de stock se mantiene: pasa al pedido recién cuando el
 *     checkout lo cree (instrucción futura).
 *   - RECHAZAR lleva la solicitud a ANULADA y libera el stock. Se permite
 *     también con la oferta vencida: sin esto, una oferta vencida (que
 *     sigue APROBADA y cuenta como "en curso") bloquearía para siempre
 *     una nueva solicitud del cliente.
 *   - Cada transición escribe su línea en solicitud_credito_historial.
 */
class OfertaCreditoService
{
    public function __construct(
        private StockCreditoService $stock,
    ) {}

    /**
    * Solicitudes con algo pendiente del cliente (número y punto rojo del menú):
    *   - APROBADA vigente: falta aceptar o rechazar.
    *   - ACEPTADA con plazo vigente: falta completar el pedido.
    */
    public function pendientes(Usuario $usuario): int
    {
        $cliente = $usuario->persona?->cliente;

        if (! $cliente) {
            return 0;
        }

        return SolicitudCredito::where('scr_cliid', $cliente->cli_id)
            ->whereNotNull('scr_vighas')
            ->whereDate('scr_vighas', '>=', now()->toDateString())
            ->where(function ($q) {
                $q->where(function ($q) {
                    $q->whereHas('estado', fn ($e) => $e->where('esc_codigo', EstadoSolicitud::APROBADA))
                      ->whereNull('scr_fecace');
                })->orWhereHas('estado', fn ($e) => $e->where('esc_codigo', EstadoSolicitud::ACEPTADA));
            })
            ->count();
    }

    /** [ACEPTAR]: APROBADA -> ACEPTADA. */
    public function aceptar(Usuario $usuario, int $id): void
    {
        DB::transaction(function () use ($usuario, $id) {
            $solicitud = $this->bloquear($usuario, $id);
            $this->exigirOfertaPendiente($solicitud);

            if (! $solicitud->ofertaVigente()) {
                abort(409, $solicitud->scr_vighas
                    ? 'La oferta venció el ' . $solicitud->scr_vighas->format('d/m/Y') . ' y ya no puede aceptarse.'
                    : 'Esta solicitud no tiene una oferta vigente.');
            }

            $anterior = (int) $solicitud->scr_escid;
            $nuevo    = EstadoSolicitud::porCodigo(EstadoSolicitud::ACEPTADA);

            $solicitud->update([
                'scr_escid'  => $nuevo->esc_id,
                'scr_fecace' => now(),
            ]);

            $this->registrarHistorial(
                $solicitud, $anterior, $nuevo->esc_id, $usuario,
                'Oferta aceptada por el cliente.'
            );
        });
    }

    /** [Rechazar oferta]: APROBADA -> ANULADA, con liberación del stock. */
    public function rechazar(Usuario $usuario, int $id): void
    {
        DB::transaction(function () use ($usuario, $id) {
            $solicitud = $this->bloquear($usuario, $id);
            $this->exigirOfertaPendiente($solicitud);

            $vencida  = $solicitud->ofertaVencida();
            $anterior = (int) $solicitud->scr_escid;
            $nuevo    = EstadoSolicitud::porCodigo(EstadoSolicitud::ANULADA);

            $solicitud->update(['scr_escid' => $nuevo->esc_id]);

            // Devuelve el stock apartado. Si el barrido creditos:expirar ya
            // lo había liberado, scr_fecres es NULL y esto no hace nada.
            $this->stock->liberar($solicitud);

            $this->registrarHistorial(
                $solicitud, $anterior, $nuevo->esc_id, $usuario,
                $vencida
                    ? 'Oferta vencida: solicitud anulada por el cliente.'
                    : 'Oferta rechazada por el cliente.'
            );
        });
    }

    // ====================================================================
    //  Auxiliares privados
    // ====================================================================

    /**
     * Trae la solicitud del cliente CON bloqueo de fila.
     *
     * - Filtra por scr_cliid: una solicitud ajena responde 404, igual que
     *   si no existiera (no se revela su existencia).
     * - lockForUpdate() pone en fila el doble clic, dos pestañas o una
     *   decisión simultánea del barrido/administrador. La segunda llamada
     *   espera, lee el estado ya cambiado y se corta en
     *   exigirOfertaPendiente().
     * - Debe llamarse DENTRO de DB::transaction().
     */
    private function bloquear(Usuario $usuario, int $id): SolicitudCredito
    {
        $cliente = $usuario->persona?->cliente;
        abort_if(! $cliente, 422, 'Tu cuenta todavía no tiene un perfil de cliente asociado.');

        $solicitud = SolicitudCredito::where('scr_cliid', $cliente->cli_id)
            ->lockForUpdate()
            ->findOrFail($id);

        // El estado se lee DESPUÉS de tomar el bloqueo.
        return $solicitud->load('estado');
    }

    /**
     * Seguridad real en el backend: los botones de Angular son experiencia
     * de usuario, no control de acceso.
     */
    private function exigirOfertaPendiente(SolicitudCredito $solicitud): void
    {
        $codigo = $solicitud->estado?->esc_codigo;

        if ($codigo === EstadoSolicitud::ACEPTADA) {
            abort(409, 'Esta oferta ya fue aceptada.');
        }

        if ($codigo !== EstadoSolicitud::APROBADA) {
            abort(409, 'Esta solicitud no tiene una oferta pendiente de respuesta.');
        }
    }

    private function registrarHistorial(
        SolicitudCredito $solicitud,
        int $estadoAnterior,
        int $estadoNuevo,
        Usuario $usuario,
        string $observacion,
    ): void {
        SolicitudCreditoHistorial::create([
            'sch_scrid'  => $solicitud->scr_id,
            'sch_escant' => $estadoAnterior,
            'sch_escnue' => $estadoNuevo,
            'sch_usuid'  => $usuario->usu_id,
            'sch_observ' => $observacion,
        ]);
    }
}