<?php

namespace App\Http\Resources\Servicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Fila de la bandeja "Cobros de servicios": el pago + lo mínimo de su solicitud. */
class CobroServicioListaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $s = $this->solicitud;
        $p = $s?->cliente?->persona;

        return [
            'spa_id'  => $this->spa_id,
            'orden'   => $this->spa_orden,
            'tipo'    => $this->spa_tipo,
            'canal'   => $this->spa_canal,
            'creado'  => $this->spa_creado?->format('d/m/Y H:i'),
            'metodo'  => $this->metodoPago?->mpa_descri,
            'metodo_codigo' => $this->metodoPago?->mpa_codigo,
            'monto'   => (float) $this->spa_monto,
            'estado'  => [
                'codigo'      => $this->estadoPago?->epa_codigo,
                'descripcion' => $this->estadoPago?->epa_descri,
            ],
            'tiene_comprobante' => $this->comprobante !== null,
            'solicitud' => [
                'sse_id'     => $s?->sse_id,
                'sse_numero' => $s?->sse_numero,
                'estado'     => $s?->estadoServicio?->ets_descri,
                'total'      => (float) $s?->sse_total,
                'sucursal'   => $s?->sucursal?->suc_nombre,
                'cliente'    => $p ? trim($p->per_nombr1 . ' ' . $p->per_apell1) : null,
                'cedula'     => $p?->per_cedula,
            ],
        ];
    }
}
