<?php

namespace App\Services\Servicio;

use App\Models\Configuracion\Empresa;
use App\Models\Servicio\SolicitudAdjunto;
use App\Models\Servicio\SolicitudServicio;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Constancias en PDF (dompdf). Documento operativo interno, no fiscal.
 *   recepcion: respaldo de la tenencia del equipo (estado físico, accesorios, fotos).
 *   retiro:    respaldo de la devolución (receptor, accesorios devueltos, garantía).
 * La clave del equipo NUNCA se imprime.
 */
class ConstanciaServicioService
{
    public const RECEPCION = 'recepcion';
    public const RETIRO    = 'retiro';

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

        $pdf = Pdf::loadView("pdf.servicio.{$tipo}", [
            's'       => $s,
            'empresa' => Empresa::query()->first(),
            'fotos'   => $s->adjuntos->where('sad_tipo', SolicitudAdjunto::INGRESO)->count(),
            'vence'   => $s->garantiaVenceEl(),
        ])->setPaper('a4');

        return $pdf->stream("constancia-{$tipo}-{$s->sse_numero}.pdf");
    }
}
