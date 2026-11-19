<?php

namespace App\Services\Credito;

use App\Models\Credito\SolicitudCredito;

/**
 * Desglose por producto de cada cuota del cronograma.
 *
 * cta_cobrar.cco_compos guarda la composicion como texto plano ("A + B"),
 * que sirve para una linea de ayuda pero no para una columna "Productos"
 * con el importe de cada uno. Este servicio reconstruye ese desglose
 * desde el detalle congelado de la solicitud, usando EXACTAMENTE la misma
 * formula que SolicitudCredito::cronogramaCalculado(): si los dos calculos
 * divergieran, la suma de la columna no daria el importe de la cuota.
 *
 * Se recalcula en vez de persistirse porque es informacion derivada: el
 * dato que manda sobre la deuda sigue siendo cta_cobrar.cco_monto.
 */
class ComposicionCuotaService
{
    /**
     * @return array<int, array<int, array{descripcion: string, cantidad: float, importe: float}>>
     *         Mapa numero de cuota => lineas de producto.
     */
    public function porCuota(?SolicitudCredito $solicitud): array
    {
        if (! $solicitud) {
            return [];
        }

        $detalles = $solicitud->relationLoaded('detalles')
            ? $solicitud->detalles
            : $solicitud->detalles()->get();

        if ($detalles->isEmpty()) {
            return [];
        }

        $plazo = (int) $detalles->max('scd_numcuo');
        $mapa  = [];

        for ($mes = 1; $mes <= $plazo; $mes++) {
            $lineas = [];

            foreach ($detalles as $detalle) {
                $cuotas = (int) $detalle->scd_numcuo;

                // Un producto a 3 cuotas no aporta nada a la cuota 4.
                if ($cuotas < $mes) {
                    continue;
                }

                $cuotaLinea = round((float) $detalle->scd_moncuo * (float) $detalle->scd_cantid);

                // La ultima cuota de la linea absorbe el redondeo, igual
                // que en el cronograma: asi la suma cierra con scd_subtot.
                $importe = ($mes === $cuotas)
                    ? round((float) $detalle->scd_subtot) - $cuotaLinea * ($cuotas - 1)
                    : $cuotaLinea;

                $lineas[] = [
                    'descripcion' => $detalle->scd_descri,
                    'cantidad'    => (float) $detalle->scd_cantid,
                    'importe'     => round((float) $importe, 2),
                ];
            }

            $mapa[$mes] = $lineas;
        }

        return $mapa;
    }
}