<?php

namespace App\Services\Facturacion;

use App\Mail\FacturaElectronicaMail;
use App\Models\Venta\VentaCab;
use App\Models\Venta\VentaFe;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Reenvia al cliente una factura electronica YA APROBADA, por el canal
 * que se indique (CORREO o WHATSAPP).
 *
 * Un solo punto de entrada para todos los canales: la pantalla tiene UN
 * boton "Reenviar factura" y el canal es un dato (el que eligio el cliente
 * en el checkout, o el que elija el administrador). Agregar un canal nuevo
 * es agregar un case en reenviar() y un metodo privado: ni la ruta ni la
 * pantalla cambian.
 *
 * Usa el XML y el KuDE que este sistema guardo al aprobar la factura: la
 * API solo manda el correo en el momento de la aprobacion y no puede
 * repetirlo (guia, capitulo 10.3: "el reenvio lo hace tu sistema").
 *
 * Reglas:
 *   - Solo se reenvia lo que la DNIT aprobo. Una factura pendiente no
 *     tiene todavia nada valido que mandar.
 *   - El destino se puede cambiar (el cliente se equivoco al escribirlo).
 *     Es solo el destino de este envio: el pedido y la factura no se tocan.
 *   - El envio es sincrono, a proposito: quien aprieta el boton necesita
 *     saber ahora si salio o no.
 *   - Un reenvio fallido NO pisa el resultado anterior de venta_fe.
 */
class FacturaEnvioService
{
    public const CANAL_CORREO   = 'CORREO';
    public const CANAL_WHATSAPP = 'WHATSAPP';

    /**
     * @return string descripcion del destino al que se envio
     *
     * @throws ValidationException si la factura, el canal o el destino no permiten enviar
     * @throws RuntimeException    si el canal no acepto el mensaje
     */
    public function reenviar(VentaCab $venta, string $canal, ?string $destino = null): string
    {
        $fe = $venta->fe;

        if (!$fe || !$fe->estaEnviado()) {
            throw ValidationException::withMessages([
                'factura' => 'La factura electrónica todavía no fue aprobada: no hay nada que reenviar.',
            ]);
        }

        return match ($canal) {
            self::CANAL_CORREO   => $this->porCorreo($venta, $fe, $destino),
            self::CANAL_WHATSAPP => throw ValidationException::withMessages([
                                'canal' => 'El envío por WhatsApp es asistido: se hace desde el panel (descargar el PDF y abrir WhatsApp).',
            ]),
            default              => throw ValidationException::withMessages([
                'canal' => 'Canal de envío no válido.',
            ]),
        };
    }

    // ---------- Canales ----------

    private function porCorreo(VentaCab $venta, VentaFe $fe, ?string $correo): string
    {
        $destino = trim((string) ($correo ?: $venta->ven_email));

        if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'destino' => 'Indique un correo válido para enviar la factura.',
            ]);
        }

        $disco = Storage::disk('local');

        if (!$fe->vfe_xml_ruta || !$disco->exists($fe->vfe_xml_ruta)) {
            throw ValidationException::withMessages([
                'factura' => 'El XML de esta factura no está disponible en el servidor.',
            ]);
        }

        // Sin KuDE se manda solo el XML: es el documento con valor legal.
        $rutaPdf = ($fe->vfe_pdf_ruta && $disco->exists($fe->vfe_pdf_ruta)) ? $fe->vfe_pdf_ruta : null;

        try {
            Mail::to($destino)->send(new FacturaElectronicaMail(
                numero:   $venta->ven_nrodoc,
                empresa:  $venta->ven_emp_razon,
                cdc:      $fe->vfe_cdc,
                nombre:   $venta->ven_razonsocial,
                rutaXml:  $fe->vfe_xml_ruta,
                rutaPdf:  $rutaPdf,
                qr:       $fe->vfe_qr,
                esPrueba: $fe->vfe_ambiente === 'simulado',
            ));
        } catch (Throwable $e) {
            // El detalle tecnico va al log; al usuario no se le muestran credenciales ni rutas.
            Log::warning('No se pudo reenviar la factura electronica por correo', [
                'venta' => $venta->ven_id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'No se pudo enviar el correo. Revise la configuración de correo del servidor e intente de nuevo.'
            );
        }

        $fe->update([
            'vfe_correo_enviado' => true,
            'vfe_correo_detalle' => "{$destino} (reenvío manual)",
            'vfe_correo_fecha'   => now(),
            'vfe_actualizado'    => now(),
        ]);

        return $destino;
    }
}