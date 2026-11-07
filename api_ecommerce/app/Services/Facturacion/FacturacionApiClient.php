<?php

namespace App\Services\Facturacion;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP de Facturacion_API. Es el UNICO lugar del sistema que
 * conoce la URL, el token y la llave (config/facturacion.php).
 *
 * Solo transporta: no decide estados ni guarda nada. Siempre devuelve una
 * RespuestaApi, tambien ante un corte de red, para que el servicio de
 * negocio tenga un unico camino de lectura.
 *
 * Seguridad: el token y la llave viajan SOLO en cabeceras sobre HTTPS y
 * nunca se escriben en logs ni en mensajes de error.
 */
class FacturacionApiClient
{
    /** POST /facturas: emite una factura (habitual, de anticipo o final con anticipo). */
    public function emitirFactura(array $payload): RespuestaApi
    {
        return $this->enviar('POST', '/facturas', $payload);
    }

    /**
     * POST /documentos: nota de credito, nota de debito, autofactura y
     * nota de remision. El tipo va en el campo "tipo" del cuerpo (guia 11).
     */
    public function emitirDocumento(array $payload): RespuestaApi
    {
        return $this->enviar('POST', '/documentos', $payload);
    }

    /** GET /facturas/{cdc}: estado registrado de un comprobante, sin archivos. */
    public function consultar(string $cdc): RespuestaApi
    {
        return $this->enviar('GET', "/facturas/{$cdc}");
    }

    /**
     * GET /facturas/{cdc}/archivos: XML y KuDE. Con entrega inmediata puede
     * responder 202 con provisional: true; ya aprobado, 200 con provisional: false.
     */
    public function obtenerArchivos(string $cdc): RespuestaApi
    {
        return $this->enviar('GET', "/facturas/{$cdc}/archivos");
    }

    /**
     * DELETE /facturas/{cdc}/archivos: avisa que los archivos ya estan
     * guardados. Contesta 204 siempre. No anula ni cancela nada.
     */
    public function confirmarArchivos(string $cdc): RespuestaApi
    {
        return $this->enviar('DELETE', "/facturas/{$cdc}/archivos");
    }

    /**
     * POST /facturas/{cdc}/correo (guia 6.6.A.4 y 10.3): pide el envio del
     * comprobante DESPUES del cobro, para documentos de entrega inmediata.
     *
     * @param array{email: string, xml: string, seguimiento: bool} $cuerpo
     */
    public function solicitarCorreo(string $cdc, array $cuerpo): RespuestaApi
    {
        return $this->enviar('POST', "/facturas/{$cdc}/correo", $cuerpo);
    }

    /** @param array<string, mixed>|null $cuerpo */
    private function enviar(string $metodo, string $ruta, ?array $cuerpo = null): RespuestaApi
    {
        $url = rtrim((string) config('facturacion.url'), '/') . $ruta;

        try {
            $respuesta = Http::withToken((string) config('facturacion.token'))
                ->withHeaders(['X-Llave' => (string) config('facturacion.llave')])
                ->acceptJson()
                ->timeout((int) config('facturacion.timeout', 120))
                ->send($metodo, $url, $cuerpo !== null ? ['json' => $cuerpo] : []);
        } catch (ConnectionException $e) {
            // Corte de red o tiempo agotado: el comprobante pudo haber salido.
            return new RespuestaApi(null, [], $e->getMessage());
        }

        $json = $respuesta->json();

        return new RespuestaApi($respuesta->status(), is_array($json) ? $json : []);
    }
}