<?php

namespace App\Services\Servicio;

use App\Models\Configuracion\Empresa;
use App\Models\Servicio\SolicitudAdjunto;
use App\Models\Servicio\SolicitudServicio;
use App\Services\Cliente\VinculacionPresencialService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Constancias en PDF (dompdf). Documento operativo interno, no fiscal.
 *   recepcion: respaldo de la tenencia del equipo (estado físico, accesorios, fotos).
 *              Si el cliente aún no tiene cuenta web, incluye el código de vinculación y su QR.
 *   retiro:    respaldo de la devolución (receptor, accesorios devueltos, garantía).
 * La clave del equipo NUNCA se imprime.
 */
class ConstanciaServicioService
{
    public const RECEPCION = 'recepcion';
    public const RETIRO    = 'retiro';

    public function __construct(private VinculacionPresencialService $vinculacion) {}

    public function generar(SolicitudServicio $s, string $tipo): Response
    {
        $s->loadMissing([
            'cliente.persona.tipoDocumento', 'cliente.persona.usuario', 'tipoDispositivo', 'sucursal', 'tipoEntrega',
            'recepcion.accesorios', 'recepcion.tecnico.persona', 'recepcion.recibidoPor.persona',
            'ordenServicio', 'entrega.usuario.persona', 'entrega.sucursal', 'adjuntos',
        ]);

        if ($tipo === self::RECEPCION && !$s->recepcion) {
            throw ValidationException::withMessages(['tipo' => 'El equipo todavía no fue recibido.']);
        }
        if ($tipo === self::RETIRO && !$s->entrega) {
            throw ValidationException::withMessages(['tipo' => 'El equipo todavía no fue entregado.']);
        }

        // Código de vinculación: solo en la constancia de recepción y si el cliente no tiene cuenta web.
        $vinculacion = null;
        if ($tipo === self::RECEPCION && $s->cliente?->persona) {
            $vinculacion = $this->vinculacion->codigoVigente($s->cliente->persona);
            if ($vinculacion) {
                $vinculacion['qr'] = $this->vinculacion->qrDataUri($vinculacion['enlace']);
            }
        }

        $pdf = Pdf::loadView("pdf.servicio.{$tipo}", [
            's'           => $s,
            'empresa'     => Empresa::query()->first(),
            'fotos'       => $s->adjuntos->where('sad_tipo', SolicitudAdjunto::INGRESO)->count(),
            'vence'       => $s->garantiaVenceEl(),
            'vinculacion' => $vinculacion,
        ])->setPaper('a4');

        return $pdf->stream("constancia-{$tipo}-{$s->sse_numero}.pdf");
    }
}