<?php

namespace App\Http\Resources\Pedido;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntregaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $e = $this->resource;

        return [
            'modalidad'     => $e['modalidad'],
            'ten_id'        => $e['ten_id']     ?? null,
            'ten_codigo'    => $e['ten_codigo'] ?? null,
            'receptor'      => $e['receptor'],

            'suc_id'        => $e['suc_id']        ?? null,
            'suc_nombre'    => $e['suc_nombre']    ?? null,
            'suc_direccion' => $e['suc_direccion'] ?? null,
            'suc_horario'   => $e['suc_horario']   ?? null,

            'dir_id'        => $e['dir_id']   ?? null,
            'departamento'  => $e['departamento'] ?? null,
            'ciudad'        => $e['ciudad']   ?? null,
            'barrio'        => $e['barrio']   ?? null,
            'calle'         => $e['calle']    ?? null,
            'nrocasa'       => $e['nrocasa']  ?? null,
            'referencia'    => $e['referencia'] ?? null,

            'rec_nrodoc'    => $e['rec_nrodoc'],
            'rec_nombre'    => $e['rec_nombre'],
            'rec_apelli'    => $e['rec_apelli'],
            'rec_telef'     => $e['rec_telef'],

            'costo_envio'   => (float) ($e['costo_envio'] ?? 0),
        ];
    }
}