<?php

namespace App\Services\Servicio;

use App\Models\Configuracion\Empresa;
use App\Models\Servicio\EstadoPresupuesto;
use App\Models\Servicio\SolicitudServicio;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Paso 2 - PDF del presupuesto para imprimir y firmar en el mostrador (dompdf, mismo patrón
 * que ConstanciaServicioService). Documento interno, sin validez fiscal.
 *
 * Se imprime el presupuesto vigente (la última versión) en ENVIADO (para firmar) y en
 * APROBADO / RECHAZADO (copia). Nunca en BORRADOR ni EN_REVISION: todavía no fue validado.
 * Muestra las fallas detectadas en el diagnóstico (no el texto libre del técnico).
 */
class PresupuestoPdfService
{
    public const IMPRIMIBLES = [EstadoPresupuesto::ENVIADO, EstadoPresupuesto::APROBADO, EstadoPresupuesto::RECHAZADO];

    public function generar(SolicitudServicio $s): Response
    {
        $s->loadMissing([
            'cliente.persona.tipoDocumento', 'tipoDispositivo', 'sucursal',
            'ordenServicio.diagnostico.fallas.falla',
            'ordenServicio.presupuestoActual.estado', 'ordenServicio.presupuestoActual.detalles',
        ]);

        $p = $s->ordenServicio?->presupuestoActual;

        if (!$p || !in_array($p->codigoEstado(), self::IMPRIMIBLES, true)) {
            throw ValidationException::withMessages([
                'presupuesto' => 'El presupuesto todavía no fue enviado al cliente: no se puede imprimir.',
            ]);
        }

        $pdf = Pdf::loadView('pdf.servicio.presupuesto', [
            's'       => $s,
            'p'       => $p,
            'fallas'  => $s->ordenServicio->diagnostico?->fallas?->map(fn ($f) => $f->falla?->fll_descri)->filter()->values() ?? collect(),
            'empresa' => Empresa::query()->first(),
        ])->setPaper('a4');

        return $pdf->stream("presupuesto-{$p->pse_numero}.pdf");
    }
}
