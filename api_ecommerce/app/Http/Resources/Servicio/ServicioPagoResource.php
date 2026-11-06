<?php

namespace App\Http\Resources\Servicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tarjeta "PAGO n" de un servicio, vista del PANEL. Misma forma que
 * PedidoPagoDetalleResource (así los modales de confirmar/rechazar se
 * reutilizan), más tipo, canal y quién cobró en el mostrador.
 *
 * Requiere cargadas: metodoPago, estadoPago, comprobante, motivoRechazo,
 * usuarioVerificador.persona, usuarioCobro.persona, sucursal.
 */
class ServicioPagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $comprobante = $this->comprobante;
        $verificado  = $this->spa_fechver !== null;

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
                'requcom' => (bool) $this->metodoPago?->mpa_requcom,
            ],

            'monto_asignado' => (float) $this->spa_monto,

            'estado' => [
                'codigo'      => $this->estadoPago?->epa_codigo,
                'descripcion' => $this->estadoPago?->epa_descri,
            ],

            'comprobante' => $comprobante ? [
                'sco_id'     => $comprobante->sco_id,
                'archivo'    => basename($comprobante->sco_archiv),
                // Por la API (con token y permiso), no por /storage.
                'url'        => url("/api/admin/servicios/cobros/{$this->spa_sseid}/pagos/{$this->spa_id}/comprobante"),
                'verificado' => (bool) $comprobante->sco_verifi,
                'subido'     => $comprobante->sco_subido?->format('d/m/Y H:i'),
            ] : null,

            'verificacion' => $verificado ? [
                'monto_verificado' => $this->spa_montver !== null ? (float) $this->spa_montver : null,
                'nro_transaccion'  => $this->spa_nrotrx,
                'banco'            => $this->spa_banco,
                'fecha_pago'       => $this->spa_fechpag?->format('d/m/Y'),
                'por'              => $this->nombre($this->usuarioVerificador),
                'fecha'            => $this->spa_fechver?->format('d/m/Y H:i'),
            ] : null,

            // Solo cuando lo cobró un funcionario (mostrador o "al retirar").
            'cobro' => $this->spa_usucob ? [
                'por'      => $this->nombre($this->usuarioCobro),
                'sucursal' => $this->sucursal?->suc_nombre,
            ] : null,

            'rechazo' => $this->spa_mrpid ? [
                'motivo_id'     => $this->spa_mrpid,
                'motivo'        => $this->motivoRechazo?->mrp_descri,
                'motivo_codigo' => $this->motivoRechazo?->mrp_codigo,
                'detalle'       => $this->spa_observ,
                'faltante'      => $this->faltante(),
            ] : null,

            'observacion' => $this->spa_mrpid ? null : $this->spa_observ,
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
