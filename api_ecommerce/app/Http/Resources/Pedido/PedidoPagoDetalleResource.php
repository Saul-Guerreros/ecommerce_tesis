<?php

namespace App\Http\Resources\Pedido;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


/**
 * Una tarjeta «PAGO n» del detalle.
 *
 * Distingue tres grupos de datos que la pantalla trata distinto:
 *   - lo que declaró el CLIENTE  (metodo, monto asignado, comprobante)
 *   - lo que comprobó el ADMIN   (verificacion.*) — null mientras el pago
 *     esté pendiente, que es lo que hace que los campos de la tarjeta se
 *     muestren vacíos hasta validar
 *   - el RECHAZO                 (motivo, detalle, diferencia faltante)
 */
class PedidoPagoDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $comprobante = $this->whenLoaded('comprobante');
        $verificado  = $this->ppa_fechver !== null;

        return [
            'ppa_id' => $this->ppa_id,
            'orden'  => $this->ppa_orden,

            'metodo' => [
                'mpa_id'   => $this->metodoPago?->mpa_id,
                'codigo'   => $this->metodoPago?->mpa_codigo,
                'descri'   => $this->metodoPago?->mpa_descri,
                'requcom'  => (bool) $this->metodoPago?->mpa_requcom,
            ],

            // Monto que el cliente asignó a esta forma de pago en el Paso 2.
            'monto_asignado' => (float) $this->ppa_monto,

            'estado' => [
                'codigo'      => $this->estadoPago?->epa_codigo,
                'descripcion' => $this->estadoPago?->epa_descri,
            ],

          'comprobante' => $comprobante
            ? [
                'pco_id'   => $comprobante->pco_id,
                'archivo'  => basename($comprobante->pco_archiv),
                // Se sirve por la API, no por /storage: así viaja con el token
                // y el archivo queda protegido por permiso.
                'url'      => url("/api/admin/pedidos/{$this->ppa_pedid}/pagos/{$this->ppa_id}/comprobante"),
                'verificado' => (bool) $comprobante->pco_verifi,
                'subido'   => $comprobante->pco_subido?->format('d/m/Y H:i'),
            ]
            : null,

            // null mientras el pago no se haya validado: la tarjeta muestra
            // los campos vacíos y toda la carga ocurre en el modal.
            'verificacion' => $verificado
                ? [
                    'monto_verificado' => $this->ppa_montver !== null ? (float) $this->ppa_montver : null,
                    'nro_transaccion'  => $this->ppa_nrotrx,
                    'banco'            => $this->ppa_banco,
                    'fecha_pago'       => $this->ppa_fechpag?->format('d/m/Y'),
                    'por'              => $this->nombreVerificador(),
                    'fecha'            => $this->ppa_fechver?->format('d/m/Y H:i'),
                ]
                : null,

            'rechazo' => $this->ppa_mrpid
                ? [
                    'motivo_id'     => $this->ppa_mrpid,
                    'motivo'        => $this->motivoRechazo?->mrp_descri,
                    'motivo_codigo' => $this->motivoRechazo?->mrp_codigo,
                    'detalle'       => $this->ppa_observ,
                    'faltante'      => $this->faltante(),
                ]
                : null,

            // Una observación sin rechazo es una nota del admin al confirmar.
            'observacion' => $this->ppa_mrpid ? null : $this->ppa_observ,
        ];
    }

    private function nombreVerificador(): ?string
    {
        $persona = $this->usuarioVerificador?->persona;

        if (!$persona) {
            return null;
        }

        return trim(($persona->per_nombr1 ?? '') . ' ' . ($persona->per_apell1 ?? '')) ?: null;
    }

    /**
     * Diferencia que el cliente todavía debe cubrir cuando el rechazo fue
     * por monto. Se recalcula acá en vez de guardarse: es derivable y
     * guardarla abriría la puerta a que quede desactualizada.
     */
    private function faltante(): ?float
    {
        if ($this->ppa_montver === null) {
            return null;
        }

        $diferencia = (float) $this->ppa_monto - (float) $this->ppa_montver;

        return $diferencia > 0 ? $diferencia : null;
    }
}