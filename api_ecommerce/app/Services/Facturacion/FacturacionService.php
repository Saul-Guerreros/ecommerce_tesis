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
 * deja el resultado en venta_fe. Implementa el capitulo 8 de la guia.
 *
 * Reglas que sostienen todo el diseno:
 *
 *   1. Nunca se declara dos veces: una venta ENVIADA no se vuelve a mandar.
 *   2. Ante cualquier duda (corte de red, 502, timeout) la venta queda
 *      PENDIENTE y se reintenta con el MISMO numero. El numero lo asigno
 *      NumeracionVentaService una sola vez y esta en venta_cab.
 *   3. Los archivos se guardan PRIMERO, el estado se anota DESPUES y
 *      recien entonces se le avisa a la API que ya los tenemos. Si algo
 *      se corta en el medio, la API los conserva 7 dias y se recuperan.
 *   4. Un RECHAZADO definitivo NO se reintenta solo: se corrige a mano.
 *
 * Este servicio NO se llama dentro de la transaccion del cobro: la llamada
 * a la API puede tardar hasta 120 s y mantendria bloqueado el timbrado.
 * Se invoca despues del commit (fase 6).
 */
class FacturacionService
{
    private const ESTADO_APROBADO  = 'APROBADO';
    private const ESTADO_RECHAZADO = 'RECHAZADO';

    public function __construct(
        private FacturaPayloadBuilder $builder,
        private FacturacionApiClient $api,
    ) {}

    // ---------- Punto de entrada ----------

    /**
     * Intenta declarar la factura. Idempotente: se puede llamar tantas
     * veces como haga falta; una venta ya ENVIADA o RECHAZADA se devuelve
     * tal cual, sin tocar la API.
     */
    public function declarar(VentaCab $venta): VentaFe
    {
        $fe = $this->obtenerRegistro($venta);

        if ($this->esFinal($fe)) {
            return $fe;
        }

        // Una factura anulada internamente no se declara.
        if ($venta->anulada()) {
            return $fe;
        }

        // Evita que dos procesos declaren la misma venta a la vez. La API
        // tambien protege (409), pero asi ni siquiera se llega a pedirlo.
        $candado = Cache::lock("fe_declarar_{$venta->ven_id}", 180);

        if (!$candado->get()) {
            return $fe;
        }

        try {
            // Otro proceso pudo haberla terminado mientras esperabamos.
            $fe->refresh();

            if ($this->esFinal($fe)) {
                return $fe;
            }

            return $this->intentar($venta, $fe);
        } finally {
            $candado->release();
        }
    }

    // ---------- Un intento ----------

    private function intentar(VentaCab $venta, VentaFe $fe): VentaFe
    {
        try {
            $payload = $this->builder->construir($venta);
        } catch (EmisionFacturaException $e) {
            // Dato que hay que corregir en el sistema: no se llama a la API.
            return $this->registrar($fe, [
                'vfe_error'   => 'payload_invalido',
                'vfe_mensaje' => $e->getMessage(),
            ]);
        }

        return $this->resolver($venta, $fe, $this->api->emitirFactura($payload));
    }

    /** Traduce la respuesta de la API a un estado (tabla del capitulo 8). */
    private function resolver(VentaCab $venta, VentaFe $fe, RespuestaApi $resp): VentaFe
    {
        // Sin respuesta: pudo haberse emitido igual. PENDIENTE, mismo numero.
        if ($resp->sinRespuesta()) {
            return $this->registrar($fe, [
                'vfe_error'   => 'sin_respuesta',
                'vfe_mensaje' => 'Sin respuesta de la API: ' . $resp->fallaRed,
            ]);
        }

        if ($resp->status === 201 && $resp->estado() === self::ESTADO_APROBADO) {
            return $this->aprobar($venta, $fe, $resp->cuerpo);
        }

        // El numero ya estaba declarado (reintento tras un corte): se recupera.
        if ($resp->status === 409
            && $resp->error() === 'numero_usado'
            && $resp->estado() === self::ESTADO_APROBADO) {
            return $this->recuperar($venta, $fe, $resp);
        }

        // Rechazo definitivo de la DNIT: se conserva el CDC y no se reintenta solo.
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

        // Todo lo demas (datos_invalidos, 401, 429, 502, 503, 202...): PENDIENTE.
        return $this->pendiente($venta, $fe, $resp);
    }

    // ---------- Resultados ----------

    /**
     * Aprobado. Orden obligatorio: guardar archivos -> anotar el estado ->
     * confirmar a la API.
     *
     * @param array<string, mixed> $c cuerpo de la respuesta (201 o recuperado)
     */
    private function aprobar(VentaCab $venta, VentaFe $fe, array $c): VentaFe
    {
        $cdc = $this->cdcValido($c['cdc'] ?? null);
        $xml = $this->decodificar($c['xml'] ?? null);

        if ($cdc === null || $xml === null) {
            // Aprobado pero la respuesta vino incompleta: se deja PENDIENTE y
            // el proximo intento lo recupera por 409 numero_usado.
            return $this->registrar($fe, [
                'vfe_error'   => 'respuesta_incompleta',
                'vfe_mensaje' => 'La API aprobo el comprobante pero no devolvio el CDC o el XML.',
            ] + $this->sinNulos(['vfe_cdc' => $cdc]));
        }

        $base    = $this->rutaBase($venta, $cdc);
        $rutaXml = $this->guardar("{$base}.xml", $xml);

        $pdf     = $this->decodificar($c['pdf'] ?? null);   // puede venir null: el XML vale igual
        $rutaPdf = $pdf !== null ? $this->guardar("{$base}.pdf", $pdf) : null;

        if ($rutaXml === null) {
            // Sin XML guardado no se confirma nada: la API lo conserva y se recupera.
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
            'vfe_ambiente' => $c['ambiente'] ?? null,
            'vfe_codigo'   => $c['codigo'] ?? null,
            'vfe_mensaje'  => $c['mensaje'] ?? null,
            'vfe_aviso'    => $this->aviso($c),
            'vfe_qr'       => $c['qr'] ?? null,
            'vfe_xml_ruta' => $rutaXml,
            'vfe_pdf_ruta' => $rutaPdf,
            'vfe_aprobado' => now(),
        ] + $this->datosCorreo($c));

        Log::info('Factura electronica aprobada', ['venta' => $venta->ven_id, 'cdc' => $cdc]);

        // Recien ahora, con los archivos en disco y el estado anotado.
        $this->confirmar($fe);

        return $fe;
    }

    /**
     * 409 numero_usado con estado APROBADO: la factura ya estaba declarada
     * (por ejemplo, la respuesta original se perdio). Se piden los archivos.
     */
    private function recuperar(VentaCab $venta, VentaFe $fe, RespuestaApi $resp): VentaFe
    {
        $cdc = $this->cdcValido($resp->dato('cdc'));

        if ($cdc === null) {
            return $this->pendiente($venta, $fe, $resp);
        }

        // Los archivos ya no estan en la API (confirmados antes o vencidos).
        // La DNIT la tiene aprobada: no se puede dejar PENDIENTE para siempre.
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

        // Ambiente y codigo no vienen en los archivos: se piden aparte
        // (mejor esfuerzo; si falla, quedan vacios).
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

    /**
     * No se pudo cerrar y no es un rechazo definitivo. Se conserva el CDC y
     * el XML si la API los devolvio (guia 8.1) y se guarda el motivo.
     */
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

    /** DELETE .../archivos. Si falla no pasa nada: la API los borra sola a los 7 dias. */
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

    // ---------- Persistencia ----------

    /** Un registro venta_fe por venta. Si dos procesos lo crean a la vez, gana el unico. */
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

        // firstOrCreate no trae los DEFAULT de la base (intentos, fechas).
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

    private function esFinal(VentaFe $fe): bool
    {
        return $fe->estaEnviado() || $fe->estaRechazado();
    }

    // ---------- Archivos ----------

    /**
     * Escribe completo a un temporal y renombra, para que nunca quede un
     * archivo a medias (guia 7.3). Disco 'local' = storage/app, privado.
     * Devuelve la ruta, o null si no se pudo escribir y verificar.
     */
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

    /** Quita los null: un dato que la API no devolvio no debe pisar el que ya habia. */
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

    /** "prueba" y "aviso" de la API juntos, para mostrarlos al usuario. */
    private function aviso(array $c): ?string
    {
        $texto = trim(($c['prueba'] ?? '') . ' ' . ($c['aviso'] ?? ''));

        return $texto !== '' ? $texto : null;
    }


     /**
     * Resultado del correo que la API intento mandar al aprobar (guia 10.3).
     * Solo viene si se intento. Un correo que no salio NO cambia el comprobante:
     * sigue aprobado, y el reenvio lo hace este sistema.
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


    /** Mensaje de la API; si trae el detalle de campos con error, lo agrega. */
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