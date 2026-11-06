<?php

namespace App\Services\Servicio;

use App\Jobs\DeclararFacturaElectronica;
use App\Models\Servicio\SolicitudServicio;
use App\Models\Venta\VentaCab;
use App\Models\Venta\VentaFe;
use App\Services\Facturacion\FacturaEnvioService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Lectura y acciones sobre la factura de una solicitud de servicio, para el
 * panel (sección "Factura" del detalle) y para la tienda (C4).
 *
 * Reutiliza sin modificar: VentaFe (estados), el job DeclararFacturaElectronica
 * (reintento) y FacturaEnvioService (reenvío por correo). Equivale a lo que
 * VentaController y PedidoGestionController hacen para el pedido.
 */
class FacturaServicioConsultaService
{
    private const ESTADOS_FE = [
        VentaFe::ESTADO_PENDIENTE => 'PENDIENTE',
        VentaFe::ESTADO_ENVIADO   => 'APROBADA',
        VentaFe::ESTADO_RECHAZADO => 'RECHAZADA',
    ];

    public function __construct(private FacturaEnvioService $envio) {}

    // ---------- Resúmenes ----------

    /** Panel: todo lo necesario para la sección "Factura". null = sin factura. */
    public function resumenPanel(SolicitudServicio $s): ?array
    {
        $venta = $this->venta($s);
        if (!$venta) {
            return null;
        }

        $fe = $venta->fe;

        return [
            'ven_id'       => $venta->ven_id,
            'numero'       => $venta->ven_nrodoc,
            'timbrado'     => $venta->ven_timbrado,
            'fecha'        => $venta->ven_creado?->toDateTimeString(),
            'total'        => (float) $venta->ven_total,
            'razon_social' => $venta->ven_razonsocial,
            'documento'    => $venta->ven_ruc ?? $venta->ven_ci,
            'tipo_doc'     => $venta->ven_ruc ? 'RUC' : 'CI',
            'email'        => $venta->ven_email,
            'telefono'     => $venta->ven_telefono,
            'recibe'       => $s->facturacion?->sfa_recibe,
            'fe'           => $fe ? [
                'estado'     => self::ESTADOS_FE[$fe->vfe_estado] ?? 'PENDIENTE',
                'cdc'        => $fe->vfe_cdc,
                'ambiente'   => $fe->vfe_ambiente,
                'prueba'     => $fe->vfe_ambiente !== null && $fe->vfe_ambiente !== 'prod',
                'mensaje'    => $fe->vfe_mensaje,
                'aviso'      => $fe->vfe_aviso,
                'error'      => $fe->vfe_error,
                'qr'         => $fe->vfe_qr,
                'intentos'   => (int) $fe->vfe_intentos,
                'tiene_pdf'  => $this->existe($fe->vfe_pdf_ruta),
                'tiene_xml'  => $this->existe($fe->vfe_xml_ruta),
                'correo'     => $fe->vfe_correo_enviado === null ? null : [
                    'enviado' => (bool) $fe->vfe_correo_enviado,
                    'detalle' => $fe->vfe_correo_detalle,
                ],
            ] : null,
            'acciones' => [
                'reintentar' => (bool) config('facturacion.activa') && $fe?->estaPendiente(),
                'reenviar'   => (bool) $fe?->estaEnviado(),
            ],
        ];
    }

    /** Tienda: solo lo que el cliente necesita ver (sin mensajes técnicos). */
    public function resumenCliente(SolicitudServicio $s): ?array
    {
        $venta = $this->venta($s);
        if (!$venta) {
            return null;
        }

        $fe = $venta->fe;

        return [
            'numero'     => $venta->ven_nrodoc,
            'fecha'      => $venta->ven_fchaem?->toDateString(),
            'total'      => (float) $venta->ven_total,
            'documento'  => $venta->ven_ruc ?? $venta->ven_ci,
            'nombre'     => $venta->ven_razonsocial,
            'cdc'        => $fe?->estaEnviado() ? $fe->vfe_cdc : null,
            'qr'         => $fe?->estaEnviado() ? $fe->vfe_qr : null,
            'prueba'     => $fe?->vfe_ambiente !== null && $fe?->vfe_ambiente !== 'prod',
            // El cliente la descarga recién cuando la DNIT la aprobó.
            'disponible' => (bool) $fe?->estaEnviado() && $this->existe($fe->vfe_pdf_ruta),
        ];
    }

    // ---------- Archivos ----------

    /**
     * @param 'pdf'|'xml' $tipo
     * @return array{ruta: string, nombre: string, mime: string}
     */
    public function archivo(SolicitudServicio $s, string $tipo): array
    {
        $venta = $this->venta($s);
        $ruta  = $tipo === 'xml' ? $venta?->fe?->vfe_xml_ruta : $venta?->fe?->vfe_pdf_ruta;

        abort_if(!$venta || !$this->existe($ruta), 404, 'El archivo de la factura no está disponible.');

        return [
            'ruta'   => $ruta,
            'nombre' => "Factura_{$venta->ven_nrodoc}.{$tipo}",
            'mime'   => $tipo === 'xml' ? 'application/xml' : 'application/pdf',
        ];
    }

    // ---------- Acciones ----------

    /** Mismo criterio que VentaController::reintentarFe(): solo encola el job. */
    public function reintentar(SolicitudServicio $s): void
    {
        $venta = $this->venta($s);

        $error = match (true) {
            !config('facturacion.activa')  => 'La facturación electrónica está desactivada.',
            !$venta                         => 'Esta solicitud no tiene factura emitida.',
            !$venta->fe                     => 'Esta factura se emitió sin facturación electrónica y no se puede declarar.',
            !$venta->fe->estaPendiente()    => 'Solo se pueden reintentar las facturas pendientes.',
            default                         => null,
        };

        if ($error) {
            throw ValidationException::withMessages(['factura' => $error]);
        }

        DeclararFacturaElectronica::dispatch($venta->ven_id);
    }

    /** @return string destino al que se envió */
    public function reenviar(SolicitudServicio $s, ?string $canal, ?string $destino): string
    {
        $venta = $this->venta($s);

        if (!$venta) {
            throw ValidationException::withMessages(['factura' => 'Esta solicitud no tiene factura emitida.']);
        }

        // Por defecto CORREO: el envío por WhatsApp es asistido (el panel descarga
        // el PDF y abre WhatsApp), FacturaEnvioService no lo manda solo.
        $canal ??= FacturaEnvioService::CANAL_CORREO;

        return $this->envio->reenviar($venta, $canal, $destino);
    }

    // ---------- Utilidades ----------

    private function venta(SolicitudServicio $s): ?VentaCab
    {
        return $s->factura()->with('fe')->first();
    }

    private function existe(?string $ruta): bool
    {
        return $ruta !== null && Storage::disk('local')->exists($ruta);
    }
}
