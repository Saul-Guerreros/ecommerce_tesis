<?php

namespace App\Http\Resources\Pedido;

use App\Models\Pedido\EstadoPago;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un pago del detalle, servido para las DOS tarjetas que lo muestran:
 *
 *   - Comprobante : lo que subió el cliente + su validación (comprobante.*)
 *   - Pago        : el cobro registrado contra la factura (cobro.*)
 *
 * verificacion.* se mantiene por compatibilidad hacia atrás: los pedidos
 * cobrados con el flujo anterior guardaron esos datos en pedido_pago y
 * siguen teniendo que mostrarse. En los pedidos nuevos viene null y el
 * dato real está en cobro.
 */
class PedidoPagoDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $comprobante = $this->whenLoaded('comprobante');
        $cobro       = $this->whenLoaded('cobro');
        $estado      = $this->estadoPago?->epa_codigo;
        $requcom     = (bool) $this->metodoPago?->mpa_requcom;
        $validado    = (bool) $comprobante?->pco_verifi;

        return [
            'ppa_id' => $this->ppa_id,
            'orden'  => $this->ppa_orden,

            'metodo' => [
                'mpa_id'   => $this->metodoPago?->mpa_id,
                'codigo'   => $this->metodoPago?->mpa_codigo,
                'descri'   => $this->metodoPago?->mpa_descri,
                'requcom'  => $requcom,
            ],

            // Monto que el cliente asignó a esta forma de pago en el Paso 2.
            'monto_asignado' => (float) $this->ppa_monto,

            'estado' => [
                'codigo'      => $estado,
                'descripcion' => $this->estadoPago?->epa_descri,
            ],

            // ---------- Tarjeta Comprobante ----------
            'comprobante' => $comprobante
                ? [
                    'pco_id'  => $comprobante->pco_id,
                    'archivo' => basename($comprobante->pco_archiv),
                    // Se sirve por la API, no por /storage: así viaja con el token
                    // y el archivo queda protegido por permiso.
                    'url'     => url("/api/admin/pedidos/{$this->ppa_pedid}/pagos/{$this->ppa_id}/comprobante"),
                    'subido'  => $comprobante->pco_subido?->format('d/m/Y H:i'),

                    'validado'   => $validado,
                    'validacion' => $comprobante->pco_fechver
                        ? [
                            'observacion' => $comprobante->pco_observ,
                            'por'         => $this->nombre($comprobante->usuarioValidador),
                            'fecha'       => $comprobante->pco_fechver->format('d/m/Y H:i'),
                        ]
                        : null,
                ]
                : null,

            // ---------- Tarjeta Pago ----------
            'cobro' => $cobro
                ? [
                    'cob_id'          => $cobro->cob_id,
                    'monto'           => (float) $cobro->cob_monto,
                    'metodo'          => $cobro->metodoPago?->mpa_descri,
                    'nro_transaccion' => $cobro->cob_nrotrx,
                    'banco'           => $cobro->cob_banco,
                    'fecha'           => $cobro->cob_fecha?->format('d/m/Y'),
                    'observacion'     => $cobro->cob_observ,
                    'por'             => $this->nombre($cobro->usuario),
                    'registrado'      => $cobro->cob_creado?->format('d/m/Y H:i'),
                ]
                : null,

            // ---------- Habilitación de los botones ----------
            // La pantalla no decide la regla: la repite. La regla real vive
            // en PagoVerificacionService y es la que manda si alguien llama
            // la API por fuera del panel.
            'puede_validar' => $requcom
                && $comprobante !== null
                && !$validado
                && !in_array($estado, [EstadoPago::CONFIRMADO, EstadoPago::RECHAZADO], true),

            'puede_cobrar' => $estado !== EstadoPago::CONFIRMADO
                && (!$requcom || $validado),

            // ---------- Compatibilidad: pedidos del flujo anterior ----------
            'verificacion' => $this->ppa_fechver !== null && $cobro === null
                ? [
                    'monto_verificado' => $this->ppa_montver !== null ? (float) $this->ppa_montver : null,
                    'nro_transaccion'  => $this->ppa_nrotrx,
                    'banco'            => $this->ppa_banco,
                    'fecha_pago'       => $this->ppa_fechpag?->format('d/m/Y'),
                    'por'              => $this->nombre($this->usuarioVerificador),
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

            // Una observación sin rechazo es una nota del admin al cobrar.
            'observacion' => $this->ppa_mrpid ? null : ($cobro?->cob_observ ?? $this->ppa_observ),
        ];
    }

    /** Nombre visible de un usuario (verificador, validador o cobrador). */
    private function nombre(mixed $usuario): ?string
    {
        $persona = $usuario?->persona;

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