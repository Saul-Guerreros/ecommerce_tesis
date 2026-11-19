<?php

namespace App\Http\Resources\Servicio;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\PagoCheque;
use App\Models\Servicio\ServicioPago;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un pago del servicio en el PANEL, para las dos tarjetas (igual que
 * PedidoPagoDetalleResource):
 *   - Comprobante: lo que subió el cliente + su validación.
 *   - Pago: el cobro registrado contra la factura (recibo).
 *
 * $ctx lo arma CobroServicioConsultaService::detalle(): qué facturas existen,
 * si tienen documento y qué puede hacer el usuario. Los puede_* habilitan
 * botones; la regla real vive en PagoServicioVerificacionService.
 *
 * verificacion.* se mantiene para los servicios cobrados con el ciclo
 * anterior (datos en servicio_pago, sin recibo).
 */
class ServicioPagoResource extends JsonResource
{
    public function __construct($resource, private array $ctx = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $comprobante = $this->comprobante;
        $cheque      = $this->relationLoaded('cheque') ? $this->cheque : null;
        $cobro       = $this->relationLoaded('cobro') ? $this->cobro : null;
        $estado      = $this->estadoPago?->epa_codigo;
        $requcom     = (bool) $this->metodoPago?->mpa_requcom;
        $validado    = (bool) $comprobante?->sco_verifi;
        $anticipo    = $this->spa_tipo === ServicioPago::TIPO_ANTICIPO;
        $web         = $this->spa_canal === ServicioPago::CANAL_WEB;
        $pendiente   = $estado === EstadoPago::PAGO_VERIFICAR;
        $facturado   = (bool) ($this->ctx[$anticipo ? 'factura_anticipo' : 'factura_final'] ?? false);
        $documento   = (bool) ($this->ctx[$anticipo ? 'documento_anticipo' : 'documento_final'] ?? false);

        // Efectivo y mostrador los cobra un cajero de la sucursal del equipo.
        $enCaja = !$web || $this->metodoPago?->mpa_codigo === MetodoPago::CONTRAENTREGA;

        return [
            'spa_id' => $this->spa_id,
            'orden'  => $this->spa_orden,
            'tipo'   => $this->spa_tipo,
            'canal'  => $this->spa_canal,
            'creado' => $this->spa_creado?->format('d/m/Y H:i'),

            'metodo' => [
                'mpa_id'  => $this->metodoPago?->mpa_id,
                'codigo'  => $this->metodoPago?->mpa_codigo,
                'descri'  => $this->metodoPago?->mpa_descri,
                'requcom' => $requcom,
            ],

            'monto_asignado' => (float) $this->spa_monto,

            'estado' => [
                'codigo'      => $estado,
                'descripcion' => $this->estadoPago?->epa_descri,
            ],

            'facturado' => $facturado,

            // ---------- Tarjeta Comprobante ----------
            'comprobante' => $comprobante ? [
                'sco_id'     => $comprobante->sco_id,
                'archivo'    => basename($comprobante->sco_archiv),
                'url'        => url("/api/admin/servicios/cobros/{$this->spa_sseid}/pagos/{$this->spa_id}/comprobante"),
                'subido'     => $comprobante->sco_subido?->format('d/m/Y H:i'),
                'validado'   => $validado,
                'validacion' => $comprobante->sco_fechver ? [
                    'observacion' => $comprobante->sco_observ,
                    'por'         => $this->nombre($comprobante->usuarioValidador),
                    'fecha'       => $comprobante->sco_fechver->format('d/m/Y H:i'),
                ] : null,
            ] : null,

            // ---------- Tarjeta Pago ----------
            'cobro' => $cobro ? [
                // La clave 'cob_id' se mantiene: el panel Angular la consume.
                'cob_id'          => $cobro->rec_id,
                'monto'           => (float) $cobro->rec_monto,
                'metodo'          => $cobro->metodoPago?->mpa_descri,
                'nro_transaccion' => $cobro->rec_nrotrx,
                'banco'           => $cobro->rec_banco,
                'fecha'           => $cobro->rec_fecha?->format('d/m/Y'),
                'observacion'     => $cobro->rec_observ,
                'por'             => $this->nombre($cobro->usuario),
                'registrado'      => $cobro->rec_creado?->format('d/m/Y H:i'),
                'puede_anular'    => (bool) ($this->ctx['anular_cobro'] ?? false),
            ] : null,

            'cheque' => $cheque ? [
                'sch_id'          => $cheque->sch_id,
                'tipo'            => $cheque->sch_tipo,
                'tipo_label'      => $cheque->descripcionTipo(),
                'diferido'        => $cheque->esDiferido(),
                'banco'           => $cheque->sch_banco,
                'nombre'          => $cheque->sch_nombre,
                'nrodoc'          => $cheque->sch_nrodoc,
                'nro'             => $cheque->sch_nro,
                'fecha_cobro'     => $cheque->sch_fecobr?->format('d/m/Y'),
                'fecha_cobro_iso' => $cheque->sch_fecobr?->format('Y-m-d'),
                'estado'          => $cheque->sch_estado,
                'estado_label'    => $cheque->descripcionEstado(),
                'vence'           => $cheque->sch_fevcto?->toIso8601String(),
                'vence_label'     => $cheque->sch_fevcto?->format('d/m H:i'),
                'vencido'         => $cheque->vencido(),
                'recepcion'       => $cheque->sch_frecep ? [
                    'fecha'       => $cheque->sch_frecep->format('d/m/Y H:i'),
                    'por'         => $this->nombre($cheque->usuarioReceptor),
                    'observacion' => $cheque->sch_motdet,
                ] : null,
                'rechazo'         => $cheque->sch_mrpid ? [
                    'motivo'  => $cheque->motivoRechazo?->mrp_descri,
                    'detalle' => $cheque->sch_motdet,
                ] : null,
                'puede_editar'    => ($this->ctx['verificar'] ?? false) && $cheque->estaPendiente(),
                'puede_descartar' => ($this->ctx['verificar'] ?? false) && $cheque->sch_estado === PagoCheque::EN_ESPERA,
                'puede_recibir'   => ($this->ctx['verificar'] ?? false) && $cheque->estaPendiente() && $cheque->datosCompletos(),
                // Pendiente, o recibido sin factura que lo declare (el papel se devuelve).
                'puede_rechazar'  => ($this->ctx['verificar'] ?? false) && $cheque->estaPendiente(),
                'falta_datos'     => $cheque->estaPendiente() && !$cheque->datosCompletos(),
            ] : null,

            // Quién lo registró en el mostrador y dónde.
            'cajero' => $this->spa_usucob ? [
                'por'      => $this->nombre($this->usuarioCobro),
                'sucursal' => $this->sucursal?->suc_nombre,
            ] : null,

            // ---------- Habilitación de botones ----------
            'puede_validar'  => ($this->ctx['verificar'] ?? false)
                && $pendiente && $comprobante !== null && !$validado,

            'puede_rechazar' => ($this->ctx['verificar'] ?? false) && $pendiente && !$facturado && $cheque === null,

            'puede_cobrar'   => $pendiente && $documento
                && (!$web || !$requcom || $validado)
                && ($cheque === null || $cheque->fueRecibido())
                && ($enCaja ? ($this->ctx['cajero'] ?? false) : ($this->ctx['gestiona'] ?? false)),

            // ---------- Compatibilidad: ciclo anterior ----------
            'verificacion' => $estado === EstadoPago::CONFIRMADO && $cobro === null && $this->spa_fechver !== null ? [
                'monto_verificado' => $this->spa_montver !== null ? (float) $this->spa_montver : null,
                'nro_transaccion'  => $this->spa_nrotrx,
                'banco'            => $this->spa_banco,
                'fecha_pago'       => $this->spa_fechpag?->format('d/m/Y'),
                'por'              => $this->nombre($this->usuarioVerificador),
                'fecha'            => $this->spa_fechver?->format('d/m/Y H:i'),
            ] : null,

            'rechazo' => $this->spa_mrpid ? [
                'motivo_id'     => $this->spa_mrpid,
                'motivo'        => $this->motivoRechazo?->mrp_descri,
                'motivo_codigo' => $this->motivoRechazo?->mrp_codigo,
                'detalle'       => $this->spa_observ,
                'faltante'      => $this->faltante(),
            ] : null,

            'observacion' => $this->spa_mrpid ? null : ($cobro?->rec_observ ?? $this->spa_observ),
        ];
    }

    private function nombre($usuario): ?string
    {
        $p = $usuario?->persona;

        return $p ? (trim(($p->per_nombr1 ?? '') . ' ' . ($p->per_apell1 ?? '')) ?: null) : null;
    }

    private function faltante(): ?float
    {
        if ($this->spa_montver === null) {
            return null;
        }

        $diferencia = (float) $this->spa_monto - (float) $this->spa_montver;

        return $diferencia > 0 ? $diferencia : null;
    }
}