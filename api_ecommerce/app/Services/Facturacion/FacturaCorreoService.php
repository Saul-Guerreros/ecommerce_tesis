<?php

namespace App\Services\Facturacion;

use App\Jobs\SolicitarCorreoFactura;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Venta\VentaCab;
use App\Models\Venta\VentaFe;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Correo del comprobante DESPUÉS del cobro (guía 0.54.1, 6.6.A.4 y 10.3).
 *
 * Las facturas de servicio se emiten con enviar_correo: false. Cuando el
 * cobro queda registrado, programar() deja una tarea DURABLE en venta_fe
 * (vfe_correo_pend) y encola su envío. Si la API o el SMTP fallan, el cobro
 * queda registrado igual y el comando facturacion:correos reintenta.
 *
 * Reglas:
 *   - Solo documentos de entrega inmediata, con CDC y archivos guardados.
 *   - Idempotente: programar dos veces no duplica (doble clic, reintento).
 *   - Se manda el XML ORIGINAL en base64; nunca se re-emite la factura.
 *   - enviado / programado de la API no prueban llegada a la bandeja.
 */
class FacturaCorreoService
{
    public function __construct(private FacturacionApiClient $api) {}

    /**
     * Deja programado el correo de la factura. Llamar DENTRO de la
     * transacción del cobro: el job sale recién después del commit.
     */
    public function programar(VentaCab $venta): bool
    {
        $fe = $venta->fe()->first();

        if (!$fe || !$fe->vfe_entinm || !$fe->tieneDocumento()) {
            return false;
        }

        if ($fe->vfe_correo_pend || $fe->vfe_correo_prog !== null) {
            return true;
        }

        if ($this->destinatario($venta) === null) {
            return false;
        }

        $fe->update(['vfe_correo_pend' => true, 'vfe_actualizado' => now()]);

        SolicitarCorreoFactura::dispatch($fe->vfe_id)->afterCommit();

        return true;
    }

    /** Un intento de POST /facturas/{cdc}/correo. */
    public function solicitar(VentaFe $fe): VentaFe
    {
        $candado = Cache::lock("fe_correo_{$fe->vfe_id}", 120);

        if (!$candado->get()) {
            return $fe;
        }

        try {
            $fe->refresh();

            if (!$fe->vfe_correo_pend) {
                return $fe;
            }

            $venta = $fe->venta;
            $email = $venta ? $this->destinatario($venta) : null;

            if ($email === null) {
                $fe->update([
                    'vfe_correo_pend'    => false,
                    'vfe_correo_detalle' => 'Sin destinatario válido o envío por correo desactivado.',
                    'vfe_correo_fecha'   => now(),
                    'vfe_actualizado'    => now(),
                ]);

                return $fe;
            }

            $xml = $this->xmlBase64($fe);

            if ($xml === null) {
                return $this->fallo($fe, 'El XML de la factura no está disponible en el servidor.');
            }

            $resp = $this->api->solicitarCorreo($fe->vfe_cdc, [
                'email'       => $email,
                'xml'         => $xml,
                'seguimiento' => true,
            ]);

            if ($resp->sinRespuesta()) {
                return $this->fallo($fe, 'Sin respuesta de la API: ' . $resp->fallaRed);
            }

            // El resumen puede venir en la raíz o dentro de "correo".
            $datos      = is_array($resp->dato('correo')) ? $resp->dato('correo') + $resp->cuerpo : $resp->cuerpo;
            $enviado    = ($datos['enviado'] ?? false) === true;
            $programado = ($datos['programado'] ?? false) === true;

            if (in_array($resp->status, [200, 202], true) && ($enviado || $programado)) {
                $fe->update([
                    'vfe_correo_pend'    => false,
                    'vfe_correo_prog'    => $programado,
                    'vfe_correo_enviado' => $enviado,
                    'vfe_correo_detalle' => $email . ($enviado ? '' : ' (seguimiento programado)'),
                    'vfe_correo_fecha'   => now(),
                    'vfe_actualizado'    => now(),
                ]);

                return $fe;
            }

            return $this->fallo($fe, (string) ($datos['motivo'] ?? $resp->dato('mensaje')
                ?? $resp->error() ?? "HTTP {$resp->status}"));
        } finally {
            $candado->release();
        }
    }

    /**
     * Correo del cliente, si corresponde enviarlo: envío habilitado, correo
     * válido y, en servicio técnico, que el cliente haya elegido recibirlo.
     */
    public function destinatario(VentaCab $venta): ?string
    {
        if (!config('facturacion.enviar_correo')) {
            return null;
        }

        $email = trim((string) $venta->ven_email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        if ($venta->ven_sseid !== null) {
            $solicitud = SolicitudServicio::with('facturacion')->find($venta->ven_sseid);

            if (!$solicitud?->facturacion?->recibePorCorreo()) {
                return null;
            }
        }

        return $email;
    }

    private function xmlBase64(VentaFe $fe): ?string
    {
        $disco = Storage::disk('local');

        if (!$fe->vfe_xml_ruta || !$disco->exists($fe->vfe_xml_ruta)) {
            return null;
        }

        return base64_encode($disco->get($fe->vfe_xml_ruta));
    }

    private function fallo(VentaFe $fe, string $motivo): VentaFe
    {
        $fe->update([
            'vfe_correo_intentos' => ((int) $fe->vfe_correo_intentos) + 1,
            'vfe_correo_detalle'  => mb_substr($motivo, 0, 1000),
            'vfe_correo_fecha'    => now(),
            'vfe_actualizado'     => now(),
        ]);

        return $fe;
    }
}