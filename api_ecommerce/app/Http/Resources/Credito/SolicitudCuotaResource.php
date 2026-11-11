<?php

namespace App\Http\Resources\Credito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila del cronograma ya persistido.
 *
 * Sirve tanto para el modelo Eloquent (cronograma guardado) como para
 * el arreglo que devuelve SolicitudCredito::cronogramaCalculado()
 * (estimacion previa a la aprobacion). Un solo Resource y no dos: la
 * pantalla consume la misma estructura en los dos casos.
 */
class SolicitudCuotaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            return [
                'numero'      => (int) $this->resource['numero'],
                'vencimiento' => $this->resource['vencimiento'],
                'importe'     => (float) $this->resource['importe'],
                'composicion' => $this->resource['composicion'],
            ];
        }

        return [
            'numero'      => (int) $this->scc_numero,
            'vencimiento' => $this->scc_fecven?->format('Y-m-d'),
            'importe'     => (float) $this->scc_import,
            'composicion' => $this->scc_compos,
        ];
    }
}