<?php

namespace App\Services\Servicio;

use App\Jobs\DeclararFacturaElectronica;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Venta\VentaCab;
use App\Models\Venta\VentaFe;
use App\Services\Facturacion\FacturaEnvioService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Models\Usuario;
use App\Services\Venta\VentaService;

/**
 * Lectura y acciones sobre las facturas de una solicitud (FINAL o ANTICIPO),
 * para el panel y para la tienda. $tipo elige cuál: VentaCab::TIPO_VENTA
 * (por defecto) o VentaCab::TIPO_ANTICIPO.
 */
class FacturaServicioConsultaService
{
    private const ESTADOS_FE = [
        VentaFe::ESTADO_PENDIENTE   => 'PENDIENTE',
        VentaFe::ESTADO_ENVIADO     => 'APROBADA',
        VentaFe::ESTADO_RECHAZADO   => 'RECHAZADA',
        VentaFe::ESTADO_PREPARADO   => 'PREPARADA',
        VentaFe::ESTADO_RETIRADO    => 'RETIRADA',
        VentaFe::ESTADO_CANCELADO   => 'CANCELADA',
        VentaFe::ESTADO_INUTILIZADO => 'INUTILIZADA',
    ];

    public function __construct(
        private FacturaEnvioService $envio,
        private VentaService $ventas,
    ) {}

    // ---------- Resúmenes ----------

    /** Panel: sección "Factura" / "Factura de anticipo". null = no existe. */
    public function resumenPanel(SolicitudServicio $s, string $tipo = VentaCab::TIPO_VENTA): ?array
    {
        $venta = $this->venta($s, $tipo);
        if (!$venta) {
            return null;
        }

        $fe = $venta->fe;

        return [
            'ven_id'       => $venta->ven_id,
            'tipo'         => $venta->ven_tipo,
            'numero'       => $venta->ven_nrodoc,
            'timbrado'     => $venta->ven_timbrado,
            'fecha'        => $venta->ven_creado?->toDateTimeString(),
            'total'        => (float) $venta->ven_total,
            'estado'       => $venta->ven_estado === VentaCab::ESTADO_COBRADA ? 'COBRADA' : 'EMITIDA',
            'cobrado'      => $venta->totalCobrado(),
            'estado_detalle' => $venta->estadoDetalle(),
            'por_devolver'   => $venta->dineroPorDevolver(),
            'anticipo'     => $this->datosAnticipo($venta),
            'razon_social' => $venta->ven_razonsocial,
            'documento'    => $venta->ven_ruc ?? $venta->ven_ci,
            'tipo_doc'     => $venta->ven_ruc ? 'RUC' : 'CI',
            'email'        => $venta->ven_email,
            'telefono'     => $venta->ven_telefono,
            'recibe'       => $s->facturacion?->sfa_recibe,
            'fe'           => $fe ? [
                'estado'      => self::ESTADOS_FE[$fe->vfe_estado] ?? 'PENDIENTE',
                'provisional' => $fe->estaPreparado(),
                'documento'   => $fe->tieneDocumento(),
                'cdc'         => $fe->vfe_cdc,
                'ambiente'    => $fe->vfe_ambiente,
                'prueba'      => $fe->vfe_ambiente !== null && $fe->vfe_ambiente !== 'prod',
                'mensaje'     => $fe->vfe_mensaje,
                'aviso'       => $fe->vfe_aviso,
                'error'       => $fe->vfe_error,
                // Pendiente: transitoria (se reintenta sola) o corregible (hay que anular y re-emitir).
                'reintentable' => $fe->esReintentable(),
                'corregible'   => $fe->estaPendiente() && $fe->vfe_error !== null && !$fe->esReintentable(),
                'sin_validez'  => $fe->sinValidez(),
                'qr'          => $fe->vfe_qr,
                'intentos'    => (int) $fe->vfe_intentos,
                'tiene_pdf'   => $this->existe($fe->vfe_pdf_ruta),
                'tiene_xml'   => $this->existe($fe->vfe_xml_ruta),
                'correo'      => $this->correo($fe),
            ] : null,
            'acciones' => [
                // RETIRADA: si se reactivó en el portal, el reintento continúa la emisión.
                'reintentar' => (bool) config('facturacion.activa') && ($fe?->estaPendiente() || $fe?->estaRetirado()),
                'reenviar'   => (bool) $fe?->estaEnviado(),
                // Condición de negocio; el permiso lo agrega CobroServicioConsultaService.
                'anular'     => $venta->anulableEnServicio(),
            ],
        ];
    }

    /** Tienda: solo lo que el cliente necesita ver. */
    public function resumenCliente(SolicitudServicio $s, string $tipo = VentaCab::TIPO_VENTA): ?array
    {
        $venta = $this->venta($s, $tipo);
        if (!$venta) {
            return null;
        }

        $fe = $venta->fe;

        return [
            'tipo'       => $venta->ven_tipo,
            'numero'     => $venta->ven_nrodoc,
            'fecha'      => $venta->ven_fchaem?->toDateString(),
            'total'      => (float) $venta->ven_total,
            'anticipo'   => $venta->ven_antvenid ? (float) $venta->ven_antapl : null,
            'documento'  => $venta->ven_ruc ?? $venta->ven_ci,
            'nombre'     => $venta->ven_razonsocial,
            'cdc'        => $fe?->estaEnviado() ? $fe->vfe_cdc : null,
            'qr'         => $fe?->estaEnviado() ? $fe->vfe_qr : null,
            'prueba'     => $fe?->vfe_ambiente !== null && $fe?->vfe_ambiente !== 'prod',
            'disponible' => (bool) $fe?->estaEnviado() && $this->existe($fe->vfe_pdf_ruta),
            'estado'     => $this->estadoCliente($fe),
        ];
    }

    // ---------- Archivos ----------

    /**
     * @param 'pdf'|'xml' $formato
     * @return array{ruta: string, nombre: string, mime: string}
     */
    public function archivo(SolicitudServicio $s, string $formato, string $tipo = VentaCab::TIPO_VENTA): array
    {
        $venta = $this->venta($s, $tipo);
        $ruta  = $formato === 'xml' ? $venta?->fe?->vfe_xml_ruta : $venta?->fe?->vfe_pdf_ruta;

        abort_if(!$venta || !$this->existe($ruta), 404, 'El archivo de la factura no está disponible.');

        $prefijo = $venta->esAnticipo() ? 'Anticipo' : 'Factura';

        return [
            'ruta'   => $ruta,
            'nombre' => "{$prefijo}_{$venta->ven_nrodoc}.{$formato}",
            'mime'   => $formato === 'xml' ? 'application/xml' : 'application/pdf',
        ];
    }

    // ---------- Acciones ----------

    public function reintentar(SolicitudServicio $s, string $tipo = VentaCab::TIPO_VENTA): void
    {
        $venta = $this->venta($s, $tipo);

        $error = match (true) {
            !config('facturacion.activa')  => 'La facturación electrónica está desactivada.',
            !$venta                         => 'Esta solicitud no tiene esa factura emitida.',
            !$venta->fe                     => 'Esta factura se emitió sin facturación electrónica y no se puede declarar.',
            !$venta->fe->estaPendiente() && !$venta->fe->estaRetirado()=> 'Solo se pueden reintentar las facturas pendientes o retiradas.',
            default                         => null,
        };

        if ($error) {
            throw ValidationException::withMessages(['factura' => $error]);
        }

        DeclararFacturaElectronica::dispatch($venta->ven_id);
    }

    /** @return string destino al que se envió */
    public function reenviar(SolicitudServicio $s, ?string $canal, ?string $destino, string $tipo = VentaCab::TIPO_VENTA): string
    {
        $venta = $this->venta($s, $tipo);

        if (!$venta) {
            throw ValidationException::withMessages(['factura' => 'Esta solicitud no tiene esa factura emitida.']);
        }

        return $this->envio->reenviar($venta, $canal ?? FacturaEnvioService::CANAL_CORREO, $destino);
    }

    // ---------- Utilidades ----------

    /** Anticipo: aplicado / disponible. Final: lo que aplicó y de qué factura. */
    private function datosAnticipo(VentaCab $venta): ?array
    {
        if ($venta->esAnticipo()) {
            return [
                'aplicado'      => $venta->totalAplicado(),
                'acreditado'    => $venta->totalAcreditado(),
                'disponible'    => $venta->disponibleAnticipo(),
                'factura_final' => $venta->aplicaciones()->vigente()->value('ven_id') ? $venta->aplicaciones()->vigente()->first()?->ven_nrodoc : null,
            ];
        }

        if ($venta->ven_antvenid) {
            $anticipo = $venta->anticipoAplicado()->with('fe')->first();

            return [
                'aplicado'         => (float) $venta->ven_antapl,
                'total_servicio'   => $venta->totalBruto(),
                'factura_anticipo' => $anticipo?->ven_nrodoc,
                'cdc_anticipo'     => $anticipo?->fe?->vfe_cdc,
            ];
        }

        return null;
    }

    private function correo(VentaFe $fe): ?array
    {
        if ($fe->vfe_correo_enviado === null && !$fe->vfe_correo_pend && $fe->vfe_correo_prog === null) {
            return null;
        }

        return [
            'enviado'    => (bool) $fe->vfe_correo_enviado,
            'pendiente'  => (bool) $fe->vfe_correo_pend,
            'programado' => (bool) $fe->vfe_correo_prog,
            'detalle'    => $fe->vfe_correo_detalle,
        ];
    }

    private function venta(SolicitudServicio $s, string $tipo): ?VentaCab
    {
        $relacion = $tipo === VentaCab::TIPO_ANTICIPO ? $s->facturaAnticipo() : $s->factura();

        return $relacion->with('fe')->first();
    }

    private function existe(?string $ruta): bool
    {
        return $ruta !== null && Storage::disk('local')->exists($ruta);
    }

        /**
     * Estado simplificado para el cliente (sin códigos de la DNIT):
     *   DISPONIBLE    aprobada.
     *   EN_PROCESO    pendiente transitoria o preparada (llega sola).
     *   CON_PROBLEMA  rechazada, o pendiente por un dato que hay que corregir.
     *   SIN_FE        emitida sin facturación electrónica.
     */
    private function estadoCliente(?VentaFe $fe): string
    {
        return match (true) {
            $fe === null           => 'SIN_FE',
            $fe->estaEnviado()     => 'DISPONIBLE',
            $fe->sinValidez()      => 'CON_PROBLEMA',
            $fe->estaPendiente() && $fe->vfe_error !== null && !$fe->esReintentable() => 'CON_PROBLEMA',
            default                => 'EN_PROCESO',
        };
    }

        /**
     * Anula la factura (final o anticipo) desde el módulo de servicio. Usa
     * VentaService::anular(), con sus guardas: FE rechazada o que nunca
     * llegó, sin NC, sin cobros, anticipo sin aplicar. Después, "Generar
     * factura" vuelve a aparecer y los datos fiscales se pueden corregir.
     */
    public function anular(SolicitudServicio $s, string $motivo, Usuario $usuario, string $tipo = VentaCab::TIPO_VENTA): VentaCab
    {
        $venta = $this->venta($s, $tipo);

        if (!$venta) {
            throw ValidationException::withMessages(['factura' => 'Esta solicitud no tiene esa factura vigente.']);
        }

        return $this->ventas->anular($venta, $usuario, $motivo);
    }

    /** Facturas anuladas de la solicitud, para trazabilidad en el panel. */
    public function anuladas(SolicitudServicio $s): array
    {
        return $s->facturasAnuladas()
            ->with(['fe', 'anuladaPor.persona:per_id,per_nombr1,per_apell1'])
            ->get()
            ->map(fn (VentaCab $v) => [
                'ven_id'   => $v->ven_id,
                'tipo'     => $v->ven_tipo,
                'numero'   => $v->ven_nrodoc,
                'fecha'    => $v->ven_creado?->format('d/m/Y H:i'),
                'total'    => (float) $v->ven_total,
                'anulada'  => $v->ven_anulado?->format('d/m/Y H:i'),
                'motivo'   => $v->ven_motanu,
                'por'      => trim(($v->anuladaPor?->persona?->per_nombr1 ?? '') . ' '
                            . ($v->anuladaPor?->persona?->per_apell1 ?? '')) ?: null,
                'fe'       => $v->fe ? [
                    'estado'  => self::ESTADOS_FE[$v->fe->vfe_estado] ?? 'PENDIENTE',
                    'mensaje' => $v->fe->vfe_mensaje,
                ] : null,
            ])
            ->values()->all();
    }
}