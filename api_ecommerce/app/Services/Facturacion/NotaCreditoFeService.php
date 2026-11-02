<?php

namespace App\Services\Facturacion;

use App\Exceptions\EmisionFacturaException;
use App\Models\Venta\NotaCreditoVenta;
use App\Models\Venta\NotaCreditoVentaFe;
use App\Services\Facturacion\Concerns\ManejaArchivosDe;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Declara una Nota de Credito de venta ante la DNIT a traves de
 * Facturacion_API (POST /documentos) y deja el resultado en
 * nota_credito_venta_fe. Implementa el capitulo 8 de la guia.
 *
 * Mismas cuatro reglas que FacturacionService, y por los mismos motivos:
 *
 *   1. Nunca se declara dos veces: una NC ENVIADA no se vuelve a mandar.
 *   2. Ante cualquier duda (corte de red, 502, timeout) queda PENDIENTE y se
 *      reintenta con el MISMO numero, que ya esta en nota_credito_venta.
 *   3. Los archivos se guardan PRIMERO, el estado se anota DESPUES y recien
 *      entonces se le avisa a la API que ya los tenemos.
 *   4. Un RECHAZADO definitivo NO se reintenta solo.
 *
 * No se llama dentro de la transaccion de emision: la llamada puede tardar
 * 120 s y mantendria bloqueado el timbrado.
 */
class NotaCreditoFeService
{
    use ManejaArchivosDe;

    private const ESTADO_APROBADO  = 'APROBADO';
    private const ESTADO_RECHAZADO = 'RECHAZADO';

    public function __construct(
        private NotaCreditoPayloadBuilder $builder,
        private FacturacionApiClient $api,
    ) {}

    // ---------- Punto de entrada ----------

    /** Idempotente: una NC ya ENVIADA o RECHAZADA se devuelve tal cual. */
    public function declarar(NotaCreditoVenta $nota): NotaCreditoVentaFe
    {
        $fe = $this->obtenerRegistro($nota);

        if ($this->esFinal($fe) || $nota->anulada()) {
            return $fe;
        }

        $candado = Cache::lock("nc_fe_declarar_{$nota->ncv_id}", 180);

        if (!$candado->get()) {
            return $fe;
        }

        try {
            $fe->refresh();

            if ($this->esFinal($fe)) {
                return $fe;
            }

            return $this->intentar($nota, $fe);
        } finally {
            $candado->release();
        }
    }

    // ---------- Un intento ----------

    private function intentar(NotaCreditoVenta $nota, NotaCreditoVentaFe $fe): NotaCreditoVentaFe
    {
        try {
            $payload = $this->builder->construir($nota);
        } catch (EmisionFacturaException $e) {
            return $this->registrar($fe, [
                'nfe_error'   => 'payload_invalido',
                'nfe_mensaje' => $e->getMessage(),
            ]);
        }

        return $this->resolver($nota, $fe, $this->api->emitirDocumento($payload));
    }

    /** Traduce la respuesta de la API a un estado (tabla del capitulo 8). */
    private function resolver(NotaCreditoVenta $nota, NotaCreditoVentaFe $fe, RespuestaApi $resp): NotaCreditoVentaFe
    {
        if ($resp->sinRespuesta()) {
            return $this->registrar($fe, [
                'nfe_error'   => 'sin_respuesta',
                'nfe_mensaje' => 'Sin respuesta de la API: ' . $resp->fallaRed,
            ]);
        }

        if ($resp->status === 201 && $resp->estado() === self::ESTADO_APROBADO) {
            return $this->aprobar($nota, $fe, $resp->cuerpo);
        }

        if ($resp->status === 409
            && $resp->error() === 'numero_usado'
            && $resp->estado() === self::ESTADO_APROBADO) {
            return $this->recuperar($nota, $fe, $resp);
        }

        if ($resp->status === 422 && $resp->estado() === self::ESTADO_RECHAZADO) {
            return $this->registrar($fe, [
                'nfe_estado'  => NotaCreditoVentaFe::ESTADO_RECHAZADO,
                'nfe_error'   => null,
                'nfe_mensaje' => $this->mensaje($resp),
            ] + $this->sinNulos([
                'nfe_cdc'      => $this->cdcValido($resp->dato('cdc')),
                'nfe_ambiente' => $resp->dato('ambiente'),
                'nfe_codigo'   => $resp->dato('codigo'),
            ]));
        }

        return $this->pendiente($nota, $fe, $resp);
    }

    // ---------- Resultados ----------

    /** @param array<string, mixed> $c */
    private function aprobar(NotaCreditoVenta $nota, NotaCreditoVentaFe $fe, array $c): NotaCreditoVentaFe
    {
        $cdc = $this->cdcValido($c['cdc'] ?? null);
        $xml = $this->decodificar($c['xml'] ?? null);

        if ($cdc === null || $xml === null) {
            return $this->registrar($fe, [
                'nfe_error'   => 'respuesta_incompleta',
                'nfe_mensaje' => 'La API aprobó el documento pero no devolvió el CDC o el XML.',
            ] + $this->sinNulos(['nfe_cdc' => $cdc]));
        }

        $base    = $this->rutaBase($nota, $cdc);
        $rutaXml = $this->guardar("{$base}.xml", $xml);

        $pdf     = $this->decodificar($c['pdf'] ?? null);   // puede venir null: el XML vale igual
        $rutaPdf = $pdf !== null ? $this->guardar("{$base}.pdf", $pdf) : null;

        if ($rutaXml === null) {
            return $this->registrar($fe, [
                'nfe_cdc'     => $cdc,
                'nfe_error'   => 'no_se_pudo_guardar',
                'nfe_mensaje' => 'No se pudo guardar el XML en el servidor. Se recuperará en el próximo intento.',
            ]);
        }

        $fe = $this->registrar($fe, [
            'nfe_estado'   => NotaCreditoVentaFe::ESTADO_ENVIADO,
            'nfe_error'    => null,
            'nfe_cdc'      => $cdc,
            'nfe_ambiente' => $c['ambiente'] ?? null,
            'nfe_codigo'   => $c['codigo'] ?? null,
            'nfe_mensaje'  => $c['mensaje'] ?? null,
            'nfe_aviso'    => $this->aviso($c),
            'nfe_qr'       => $c['qr'] ?? null,
            'nfe_xml_ruta' => $rutaXml,
            'nfe_pdf_ruta' => $rutaPdf,
            'nfe_aprobado' => now(),
        ] + $this->datosCorreo($c));

        Log::info('Nota de crédito electrónica aprobada', ['nota' => $nota->ncv_id, 'cdc' => $cdc]);

        $this->confirmar($fe);

        return $fe;
    }

    /** 409 numero_usado con estado APROBADO: ya estaba declarada. Se piden los archivos. */
    private function recuperar(NotaCreditoVenta $nota, NotaCreditoVentaFe $fe, RespuestaApi $resp): NotaCreditoVentaFe
    {
        $cdc = $this->cdcValido($resp->dato('cdc'));

        if ($cdc === null) {
            return $this->pendiente($nota, $fe, $resp);
        }

        if ($resp->dato('archivos') !== true) {
            return $this->registrar($fe, [
                'nfe_estado'   => NotaCreditoVentaFe::ESTADO_ENVIADO,
                'nfe_error'    => 'sin_archivos',
                'nfe_cdc'      => $cdc,
                'nfe_mensaje'  => 'Aprobada ante la DNIT, pero la API ya no conserva sus archivos. '
                                . 'Reconstruir el KuDE o consultar el documento.',
                'nfe_aprobado' => now(),
            ]);
        }

        $archivos = $this->api->obtenerArchivos($cdc);

        if ($archivos->status !== 200) {
            return $this->pendiente($nota, $fe, $archivos);
        }

        $registro = $this->api->consultar($cdc);

        return $this->aprobar($nota, $fe, [
            'cdc'      => $cdc,
            'xml'      => $archivos->dato('xml'),
            'pdf'      => $archivos->dato('pdf'),
            'qr'       => $archivos->dato('qr'),
            'ambiente' => $registro->dato('ambiente'),
            'codigo'   => $registro->dato('codigo'),
            'mensaje'  => $registro->dato('mensaje'),
        ]);
    }

    /** No se pudo cerrar y no es un rechazo definitivo: se conserva el CDC y el XML si vinieron. */
    private function pendiente(NotaCreditoVenta $nota, NotaCreditoVentaFe $fe, RespuestaApi $resp): NotaCreditoVentaFe
    {
        $cambios = [
            'nfe_error'   => $resp->error() ?? "http_{$resp->status}",
            'nfe_mensaje' => $this->mensaje($resp),
        ];

        $cdc = $this->cdcValido($resp->dato('cdc'));

        if ($cdc !== null) {
            $cambios['nfe_cdc'] = $cdc;

            $xml = $this->decodificar($resp->dato('xml'));

            if ($xml !== null) {
                $cambios['nfe_xml_ruta'] = $this->guardar($this->rutaBase($nota, $cdc) . '.xml', $xml);
            }
        }

        return $this->registrar($fe, $cambios);
    }

    /** DELETE .../archivos. Si falla no pasa nada: la API los borra sola a los 7 dias. */
    private function confirmar(NotaCreditoVentaFe $fe): void
    {
        $resp = $this->api->confirmarArchivos($fe->nfe_cdc);

        if ($resp->status === 204) {
            $fe->update(['nfe_confirmado' => now(), 'nfe_actualizado' => now()]);

            return;
        }

        Log::warning('No se pudo confirmar la recepción de archivos de la NC a la API', [
            'cdc'    => $fe->nfe_cdc,
            'status' => $resp->status,
        ]);
    }

    // ---------- Persistencia ----------

    private function obtenerRegistro(NotaCreditoVenta $nota): NotaCreditoVentaFe
    {
        try {
            $fe = NotaCreditoVentaFe::firstOrCreate(
                ['nfe_ncvid' => $nota->ncv_id],
                ['nfe_estado' => NotaCreditoVentaFe::ESTADO_PENDIENTE]
            );
        } catch (QueryException) {
            $fe = NotaCreditoVentaFe::where('nfe_ncvid', $nota->ncv_id)->firstOrFail();
        }

        return $fe->wasRecentlyCreated ? $fe->refresh() : $fe;
    }

    private function registrar(NotaCreditoVentaFe $fe, array $cambios): NotaCreditoVentaFe
    {
        $fe->update($cambios + [
            'nfe_intentos'       => ((int) $fe->nfe_intentos) + 1,
            'nfe_ultimo_intento' => now(),
            'nfe_actualizado'    => now(),
        ]);

        return $fe;
    }

    private function esFinal(NotaCreditoVentaFe $fe): bool
    {
        return $fe->estaEnviado() || $fe->estaRechazado();
    }

    private function rutaBase(NotaCreditoVenta $nota, string $cdc): string
    {
        return sprintf(
            'notas-credito-electronicas/%s/%s_%s',
            $nota->ncv_fecha->format('Y'),
            $nota->ncv_nrodoc,
            $cdc
        );
    }

    // ---------- Utilidades propias ----------

    /** Resultado del correo que la API intentó mandar al aprobar (guia 10.3). */
    private function datosCorreo(array $c): array
    {
        $correo = $c['correo'] ?? null;

        if (!is_array($correo) || !array_key_exists('enviado', $correo)) {
            return [];
        }

        $enviado = (bool) $correo['enviado'];

        return [
            'nfe_correo_enviado' => $enviado,
            'nfe_correo_detalle' => $enviado ? ($correo['a'] ?? null) : ($correo['motivo'] ?? null),
            'nfe_correo_fecha'   => now(),
        ];
    }

    private function mensaje(RespuestaApi $resp): string
    {
        $mensaje = (string) ($resp->dato('mensaje') ?? "HTTP {$resp->status}");
        $campos  = $resp->dato('campos');

        if (is_array($campos) && $campos !== []) {
            $mensaje .= ' ' . json_encode($campos, JSON_UNESCAPED_UNICODE);
        }

        return $mensaje;
    }
}