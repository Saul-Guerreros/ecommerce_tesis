<?php

namespace App\Services\Facturacion;

use App\Exceptions\EmisionFacturaException;
use App\Models\Venta\VentaCab;
use App\Models\Venta\VentaFe;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Declara una factura de venta ante la DNIT a traves de Facturacion_API y
 * deja el resultado en venta_fe. Capitulos 8 y 18 de la guia (0.54.1).
 *
 * Reglas que sostienen todo el diseno:
 *
 *   1. Nunca se declara dos veces: una venta ENVIADA no se vuelve a mandar.
 *   2. Ante cualquier duda (corte de red, 502, timeout) la venta queda
 *      PENDIENTE y se reintenta con el MISMO numero.
 *   3. Los archivos se guardan PRIMERO, el estado se anota DESPUES y
 *      recien entonces se confirma a la API.
 *   4. Un RECHAZADO definitivo NO se reintenta solo: se corrige a mano.
 *
 * Entrega inmediata (facturas de servicio):
 *   - 201 APROBADO en simulado/test: prueba local aprobada, sin DNIT.
 *   - 202 PREPARADO en prod: CDC y archivos PROVISIONALES. Se guardan y la
 *     venta queda PREPARADA (estado 4): alcanza para registrar el cobro,
 *     pero NO es aprobacion. No se confirman los archivos todavia.
 *   - El seguimiento (seguir) consulta el CDC hasta APROBADO o RECHAZADO,
 *     reemplaza los archivos por los definitivos y recien ahi confirma.
 *   - Un 202 sin CDC o sin archivos no alcanza (guia 6.6.A): PENDIENTE.
 *
 * No se llama dentro de una transaccion: la API puede tardar hasta 120 s.
 */
class FacturacionService
{
    private const ESTADO_APROBADO  = 'APROBADO';
    private const ESTADO_RECHAZADO = 'RECHAZADO';
    private const ESTADO_PREPARADO = 'PREPARADO';

        /** Estados de la API sin validez (guía 10.3) => vfe_estado. */
    private const ESTADOS_SIN_VALIDEZ = [
        'RETIRADO'    => VentaFe::ESTADO_RETIRADO,
        'CANCELADO'   => VentaFe::ESTADO_CANCELADO,
        'INUTILIZADO' => VentaFe::ESTADO_INUTILIZADO,
    ];

    public function __construct(
        private FacturaPayloadBuilder $builder,
        private FacturacionApiClient $api,
        private FacturaCorreoService $correo,
    ) {}

    // ---------- Punto de entrada ----------

    /**
     * Declara la factura o, si ya esta PREPARADA, sigue su aprobacion.
     * Idempotente: una venta ENVIADA o RECHAZADA se devuelve sin tocar la API.
     */
    public function declarar(VentaCab $venta): VentaFe
    {
        $fe = $this->obtenerRegistro($venta);

        if ($this->esFinal($fe)) {
            return $fe;
        }

        if ($venta->anulada()) {
            return $fe;
        }

        $candado = Cache::lock("fe_declarar_{$venta->ven_id}", 180);

        if (!$candado->get()) {
            return $fe;
        }

        try {
            $fe->refresh();

            if ($this->esFinal($fe)) {
                return $fe;
            }

            return $fe->estaPreparado()
                ? $this->seguir($venta, $fe)
                : $this->intentar($venta, $fe);
        } finally {
            $candado->release();
        }
    }

    // ---------- Un intento de emision ----------

    private function intentar(VentaCab $venta, VentaFe $fe): VentaFe
    {
        try {
            $payload = $this->builder->construir($venta);
        } catch (EmisionFacturaException $e) {
            return $this->registrar($fe, [
                'vfe_error'   => 'payload_invalido',
                'vfe_mensaje' => $e->getMessage(),
            ]);
        }

        // Habilita POST /correo despues del cobro (solo entrega inmediata).
        if (($payload['entrega_inmediata'] ?? false) === true && !$fe->vfe_entinm) {
            $fe->update(['vfe_entinm' => true]);
        }

        return $this->resolver($venta, $fe, $this->api->emitirFactura($payload));
    }

    /** Traduce la respuesta de la API a un estado (capitulos 8 y 18.3). */
    private function resolver(VentaCab $venta, VentaFe $fe, RespuestaApi $resp): VentaFe
    {
        if ($resp->sinRespuesta()) {
            return $this->registrar($fe, [
                'vfe_error'   => 'sin_respuesta',
                'vfe_mensaje' => 'Sin respuesta de la API: ' . $resp->fallaRed,
            ]);
        }

        // 201 ordinario o prueba local con entrega inmediata.
        if (in_array($resp->status, [200, 201], true) && $resp->estado() === self::ESTADO_APROBADO) {
            return $this->aprobar($venta, $fe, $resp->cuerpo);
        }

        // Produccion con entrega inmediata: provisional, aprobacion en curso.
        if ($resp->status === 202 && $resp->estado() === self::ESTADO_PREPARADO) {
            return $this->preparar($venta, $fe, $resp->cuerpo);
        }

        // El numero ya estaba declarado (reintento tras un corte): se recupera.
        if ($resp->status === 409 && $resp->error() === 'numero_usado') {
            if ($resp->estado() === self::ESTADO_APROBADO) {
                return $this->recuperar($venta, $fe, $resp);
            }

            if ($resp->estado() === self::ESTADO_PREPARADO) {
                return $this->recuperarPreparado($venta, $fe, $resp);
            }
        }

                // Retirado / cancelado / inutilizado: no es pendiente ni vigente.
        if ($resp->error() === 'envio_retirado') {
            return $this->marcarSinValidez($fe, VentaFe::ESTADO_RETIRADO, $resp);
        }

        if (isset(self::ESTADOS_SIN_VALIDEZ[$resp->estado()])) {
            return $this->marcarSinValidez($fe, self::ESTADOS_SIN_VALIDEZ[$resp->estado()], $resp);
        }

        if ($resp->status === 422 && $resp->estado() === self::ESTADO_RECHAZADO) {
            return $this->registrar($fe, [
                'vfe_estado'  => VentaFe::ESTADO_RECHAZADO,
                'vfe_error'   => null,
                'vfe_mensaje' => $this->mensaje($resp),
            ] + $this->sinNulos([
                'vfe_cdc'      => $this->cdcValido($resp->dato('cdc')),
                'vfe_ambiente' => $resp->dato('ambiente'),
                'vfe_codigo'   => $resp->dato('codigo'),
            ]));
        }

        return $this->pendiente($venta, $fe, $resp);
    }

    // ---------- Resultados ----------

    /**
     * Aprobado. Orden obligatorio: guardar archivos -> anotar el estado ->
     * confirmar a la API.
     *
     * @param array<string, mixed> $c
     */
    private function aprobar(VentaCab $venta, VentaFe $fe, array $c): VentaFe
    {
        $cdc = $this->cdcValido($c['cdc'] ?? null);
        $xml = $this->decodificar($c['xml'] ?? null);

        if ($cdc === null || $xml === null) {
            return $this->registrar($fe, [
                'vfe_error'   => 'respuesta_incompleta',
                'vfe_mensaje' => 'La API aprobo el comprobante pero no devolvio el CDC o el XML.',
            ] + $this->sinNulos(['vfe_cdc' => $cdc]));
        }

        $base    = $this->rutaBase($venta, $cdc);
        $rutaXml = $this->guardar("{$base}.xml", $xml);

        $pdf     = $this->decodificar($c['pdf'] ?? null);
        $rutaPdf = $pdf !== null ? $this->guardar("{$base}.pdf", $pdf) : $fe->vfe_pdf_ruta;

        if ($rutaXml === null) {
            return $this->registrar($fe, [
                'vfe_cdc'     => $cdc,
                'vfe_error'   => 'no_se_pudo_guardar',
                'vfe_mensaje' => 'No se pudo guardar el XML en el servidor. Se recuperara en el proximo intento.',
            ]);
        }

        $fe = $this->registrar($fe, [
            'vfe_estado'   => VentaFe::ESTADO_ENVIADO,
            'vfe_error'    => null,
            'vfe_cdc'      => $cdc,
            'vfe_mensaje'  => $c['mensaje'] ?? null,
            'vfe_aviso'    => $this->aviso($c),
            'vfe_xml_ruta' => $rutaXml,
            'vfe_pdf_ruta' => $rutaPdf,
            'vfe_aprobado' => now(),
        ] + $this->sinNulos([
            'vfe_ambiente' => $c['ambiente'] ?? null,
            'vfe_codigo'   => $c['codigo'] ?? null,
            'vfe_qr'       => $c['qr'] ?? null,
        ]) + $this->datosCorreo($c));

        Log::info('Factura electronica aprobada', ['venta' => $venta->ven_id, 'cdc' => $cdc]);

        $this->confirmar($fe);
        $this->programarCorreoSiCobrada($venta);

        return $fe;
    }

    /**
     * 202 PREPARADO: se guardan CDC y archivos provisionales. Sin los tres
     * no hay documento para respaldar un cobro: queda PENDIENTE y el
     * reintento con el mismo numero lo recupera por 409.
     *
     * @param array<string, mixed> $c
     */
    private function preparar(VentaCab $venta, VentaFe $fe, array $c): VentaFe
    {
        $cdc = $this->cdcValido($c['cdc'] ?? null);
        $xml = $this->decodificar($c['xml'] ?? null);
        $pdf = $this->decodificar($c['pdf'] ?? null);

        if ($cdc === null || $xml === null || $pdf === null) {
            return $this->registrar($fe, [
                'vfe_error'   => 'http_202',
                'vfe_mensaje' => 'La API preparo el comprobante sin entregar CDC, XML y PDF. Se reintentara.',
            ] + $this->sinNulos(['vfe_cdc' => $cdc]));
        }

        $base    = $this->rutaBase($venta, $cdc);
        $rutaXml = $this->guardar("{$base}.xml", $xml);
        $rutaPdf = $this->guardar("{$base}.pdf", $pdf);

        if ($rutaXml === null || $rutaPdf === null) {
            return $this->registrar($fe, [
                'vfe_cdc'     => $cdc,
                'vfe_error'   => 'no_se_pudo_guardar',
                'vfe_mensaje' => 'No se pudieron guardar los archivos provisionales. Se recuperaran en el proximo intento.',
            ]);
        }

        $fe = $this->registrar($fe, [
            'vfe_estado'   => VentaFe::ESTADO_PREPARADO,
            'vfe_error'    => null,
            'vfe_cdc'      => $cdc,
            'vfe_mensaje'  => $c['mensaje'] ?? 'Preparada: aprobacion de la DNIT en curso.',
            'vfe_aviso'    => $this->aviso($c),
            'vfe_xml_ruta' => $rutaXml,
            'vfe_pdf_ruta' => $rutaPdf,
        ] + $this->sinNulos([
            'vfe_ambiente' => $c['ambiente'] ?? null,
            'vfe_codigo'   => $c['codigo'] ?? null,
            'vfe_qr'       => $c['qr'] ?? null,
        ]) + $this->datosCorreo($c));

        Log::info('Factura electronica preparada (provisional)', ['venta' => $venta->ven_id, 'cdc' => $cdc]);
        $this->programarCorreoSiCobrada($venta);
        return $fe;
    }

    /**
     * Seguimiento de una PREPARADA: consulta el CDC.
     *   APROBADO  -> archivos definitivos, ENVIADO y confirmacion.
     *   RECHAZADO -> RECHAZADO (requiere revision: pudo haberse cobrado).
     *   otro      -> sigue PREPARADA; el barrido vuelve en 10 minutos.
     * No suma vfe_intentos: esperar a la DNIT no es un intento fallido.
     */
    private function seguir(VentaCab $venta, VentaFe $fe): VentaFe
    {
        $registro = $this->api->consultar($fe->vfe_cdc);

        if ($registro->estado() === self::ESTADO_APROBADO) {
            $archivos = $this->api->obtenerArchivos($fe->vfe_cdc);

            if ($archivos->status === 200 && $archivos->dato('provisional') !== true) {
                return $this->aprobar($venta, $fe, [
                    'cdc'      => $fe->vfe_cdc,
                    'xml'      => $archivos->dato('xml'),
                    'pdf'      => $archivos->dato('pdf'),
                    'qr'       => $archivos->dato('qr'),
                    'ambiente' => $registro->dato('ambiente'),
                    'codigo'   => $registro->dato('codigo'),
                    'mensaje'  => $registro->dato('mensaje'),
                ]);
            }

            // La copia temporal ya no esta: el XML firmado guardado es el mismo
            // documento (mismo CDC). Se registra la aprobacion sin confirmar.
            if ($archivos->status === 404) {
                $fe->update([
                    'vfe_estado'      => VentaFe::ESTADO_ENVIADO,
                    'vfe_aprobado'    => now(),
                    'vfe_codigo'      => $registro->dato('codigo') ?? $fe->vfe_codigo,
                    'vfe_mensaje'     => 'Aprobada por la DNIT. La API ya no conserva los archivos definitivos: '
                                       . 'se mantienen los guardados al prepararla.',
                    'vfe_ultimo_intento' => now(),
                    'vfe_actualizado' => now(),
                ]);

                return $fe;
            }

            return $this->marcarSeguimiento($fe, 'Aprobada; los archivos definitivos todavia no estan disponibles.');
        }

        if ($registro->estado() === self::ESTADO_RECHAZADO) {
            Log::warning('Factura preparada RECHAZADA por la DNIT', [
                'venta' => $venta->ven_id,
                'cdc'   => $fe->vfe_cdc,
            ]);

            $fe->update([
                'vfe_estado'         => VentaFe::ESTADO_RECHAZADO,
                'vfe_codigo'         => $registro->dato('codigo') ?? $fe->vfe_codigo,
                'vfe_mensaje'        => $this->mensaje($registro),
                'vfe_ultimo_intento' => now(),
                'vfe_actualizado'    => now(),
            ]);

            return $fe;
        }

        if (isset(self::ESTADOS_SIN_VALIDEZ[$registro->estado()])) {
            return $this->marcarSinValidez($fe, self::ESTADOS_SIN_VALIDEZ[$registro->estado()], $registro);
        }

        return $this->marcarSeguimiento($fe, $registro->sinRespuesta()
            ? 'Sin respuesta al consultar la aprobacion. Se volvera a consultar.'
            : (string) ($registro->dato('mensaje') ?? 'Aprobacion de la DNIT en curso.'));
    }

    /** 409 numero_usado con APROBADO: la respuesta original se perdio. */
    private function recuperar(VentaCab $venta, VentaFe $fe, RespuestaApi $resp): VentaFe
    {
        $cdc = $this->cdcValido($resp->dato('cdc'));

        if ($cdc === null) {
            return $this->pendiente($venta, $fe, $resp);
        }

        if ($resp->dato('archivos') !== true) {
            return $this->registrar($fe, [
                'vfe_estado'   => VentaFe::ESTADO_ENVIADO,
                'vfe_error'    => 'sin_archivos',
                'vfe_cdc'      => $cdc,
                'vfe_mensaje'  => 'Aprobada ante la DNIT, pero la API ya no conserva sus archivos. '
                                . 'Reconstruir el KuDE o consultar el documento.',
                'vfe_aprobado' => now(),
            ]);
        }

        $archivos = $this->api->obtenerArchivos($cdc);

        if ($archivos->status !== 200) {
            return $this->pendiente($venta, $fe, $archivos);
        }

        $registro = $this->api->consultar($cdc);

        return $this->aprobar($venta, $fe, [
            'cdc'      => $cdc,
            'xml'      => $archivos->dato('xml'),
            'pdf'      => $archivos->dato('pdf'),
            'qr'       => $archivos->dato('qr'),
            'ambiente' => $registro->dato('ambiente'),
            'codigo'   => $registro->dato('codigo'),
            'mensaje'  => $registro->dato('mensaje'),
        ]);
    }

    /** 409 numero_usado con PREPARADO: se recuperan los archivos provisionales. */
    private function recuperarPreparado(VentaCab $venta, VentaFe $fe, RespuestaApi $resp): VentaFe
    {
        $cdc = $this->cdcValido($resp->dato('cdc'));

        if ($cdc === null) {
            return $this->pendiente($venta, $fe, $resp);
        }

        $archivos = $this->api->obtenerArchivos($cdc);

        if (!in_array($archivos->status, [200, 202], true)) {
            return $this->pendiente($venta, $fe, $archivos);
        }

        $c = ['cdc' => $cdc] + $archivos->cuerpo;

        return ($archivos->status === 200 && $archivos->estado() === self::ESTADO_APROBADO)
            ? $this->aprobar($venta, $fe, $c)
            : $this->preparar($venta, $fe, $c);
    }

    private function pendiente(VentaCab $venta, VentaFe $fe, RespuestaApi $resp): VentaFe
    {
        $cambios = [
            'vfe_error'   => $resp->error() ?? "http_{$resp->status}",
            'vfe_mensaje' => $this->mensaje($resp),
        ];

        $cdc = $this->cdcValido($resp->dato('cdc'));

        if ($cdc !== null) {
            $cambios['vfe_cdc'] = $cdc;

            $xml = $this->decodificar($resp->dato('xml'));

            if ($xml !== null) {
                $cambios['vfe_xml_ruta'] = $this->guardar($this->rutaBase($venta, $cdc) . '.xml', $xml);
            }
        }

        return $this->registrar($fe, $cambios);
    }

    /** DELETE .../archivos. Si falla no pasa nada: la API los borra sola. */
    private function confirmar(VentaFe $fe): void
    {
        $resp = $this->api->confirmarArchivos($fe->vfe_cdc);

        if ($resp->status === 204) {
            $fe->update(['vfe_confirmado' => now(), 'vfe_actualizado' => now()]);

            return;
        }

        Log::warning('No se pudo confirmar la recepcion de archivos a la API', [
            'cdc'    => $fe->vfe_cdc,
            'status' => $resp->status,
        ]);
    }

        /**
     * Documento sin validez ante la API. Conserva CDC y archivos (son
     * historia), pero no respalda cobros ni se presenta como vigente.
     */
    private function marcarSinValidez(VentaFe $fe, int $estado, RespuestaApi $resp): VentaFe
    {
        Log::warning('Documento electrónico sin validez', [
            'venta_fe' => $fe->vfe_id,
            'estado'   => $estado,
            'cdc'      => $fe->vfe_cdc,
        ]);

        return $this->registrar($fe, [
            'vfe_estado'  => $estado,
            'vfe_error'   => null,
            'vfe_mensaje' => $this->mensaje($resp),
        ] + $this->sinNulos([
            'vfe_cdc'    => $this->cdcValido($resp->dato('cdc')),
            'vfe_codigo' => $resp->dato('codigo'),
        ]));
    }

    // ---------- Persistencia ----------

    private function obtenerRegistro(VentaCab $venta): VentaFe
    {
        try {
            $fe = VentaFe::firstOrCreate(
                ['vfe_venid' => $venta->ven_id],
                ['vfe_estado' => VentaFe::ESTADO_PENDIENTE]
            );
        } catch (QueryException) {
            $fe = VentaFe::where('vfe_venid', $venta->ven_id)->firstOrFail();
        }

        return $fe->wasRecentlyCreated ? $fe->refresh() : $fe;
    }

    /** Anota el intento y los cambios. Todo intento cuenta, salga bien o mal. */
    private function registrar(VentaFe $fe, array $cambios): VentaFe
    {
        $fe->update($cambios + [
            'vfe_intentos'       => ((int) $fe->vfe_intentos) + 1,
            'vfe_ultimo_intento' => now(),
            'vfe_actualizado'    => now(),
        ]);

        return $fe;
    }

    /** Factura ya cobrada (p. ej. final cubierta por el anticipo): pide su correo. */
    private function programarCorreoSiCobrada(VentaCab $venta): void
    {
        if ($venta->refresh()->ven_estado === VentaCab::ESTADO_COBRADA) {
            $this->correo->programar($venta);
        }
    }

    /** Seguimiento sin cambio de estado: anota la consulta, no suma intentos. */
    private function marcarSeguimiento(VentaFe $fe, string $mensaje): VentaFe
    {
        $fe->update([
            'vfe_mensaje'        => $mensaje,
            'vfe_ultimo_intento' => now(),
            'vfe_actualizado'    => now(),
        ]);

        return $fe;
    }

    /**
     * No se vuelve a declarar. RETIRADO no es final: si se reactivó en el
     * portal, un reintento manual continúa la emisión (o recibe otra vez
     * 409 envio_retirado).
     */
    private function esFinal(VentaFe $fe): bool
    {
        return $fe->estaEnviado() || $fe->estaRechazado() || $fe->estaCancelado() || $fe->estaInutilizado();
    }

    // ---------- Archivos ----------

    private function guardar(string $ruta, string $contenido): ?string
    {
        $disco = Storage::disk('local');
        $tmp   = $ruta . '.tmp';

        if (!$disco->put($tmp, $contenido)) {
            return null;
        }

        if ($disco->exists($ruta)) {
            $disco->delete($ruta);
        }

        if (!$disco->move($tmp, $ruta)) {
            $disco->delete($tmp);

            return null;
        }

        return $disco->size($ruta) === strlen($contenido) ? $ruta : null;
    }

    private function rutaBase(VentaCab $venta, string $cdc): string
    {
        return sprintf(
            'facturas-electronicas/%s/%s_%s',
            $venta->ven_fchaem->format('Y'),
            $venta->ven_nrodoc,
            $cdc
        );
    }

    // ---------- Utilidades ----------

    private function sinNulos(array $datos): array
    {
        return array_filter($datos, fn ($v) => $v !== null);
    }

    private function cdcValido(mixed $cdc): ?string
    {
        return is_string($cdc) && preg_match('/^[0-9]{44}$/', $cdc) ? $cdc : null;
    }

    private function decodificar(mixed $base64): ?string
    {
        if (!is_string($base64) || $base64 === '') {
            return null;
        }

        $binario = base64_decode($base64, true);

        return ($binario === false || $binario === '') ? null : $binario;
    }

    private function aviso(array $c): ?string
    {
        $texto = trim(($c['prueba'] ?? '') . ' ' . ($c['aviso'] ?? ''));

        return $texto !== '' ? $texto : null;
    }

    /**
     * Resumen del correo informado por la API (guia 10.3). Un correo que no
     * salio NO cambia el comprobante.
     */
    private function datosCorreo(array $c): array
    {
        $correo = $c['correo'] ?? null;

        if (!is_array($correo) || !array_key_exists('enviado', $correo)) {
            return [];
        }

        $enviado = (bool) $correo['enviado'];

        return [
            'vfe_correo_enviado' => $enviado,
            'vfe_correo_detalle' => $enviado ? ($correo['a'] ?? null) : ($correo['motivo'] ?? null),
            'vfe_correo_fecha'   => now(),
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