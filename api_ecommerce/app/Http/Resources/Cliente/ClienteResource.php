<?php

namespace App\Http\Resources\Cliente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cli_id'     => $this->cli_id,
            'cli_ruc'    => $this->cli_ruc,
            'cli_estado' => $this->cli_estado,

            'persona' => [
                'per_id'          => $this->persona->per_id,
                'per_tpdid'       => $this->persona->per_tpdid,
                'per_cedula'      => $this->persona->per_cedula,
                'per_nombr1'      => $this->persona->per_nombr1,
                'per_nombr2'      => $this->persona->per_nombr2,
                'per_apell1'      => $this->persona->per_apell1,
                'per_apell2'      => $this->persona->per_apell2,
                'per_estciv'      => $this->persona->per_estciv,
                'per_fechna'      => $this->persona->per_fechna?->format('Y-m-d'),
                'nombre_completo' => $this->persona->nombre_completo,
            ],

            'tipo_cliente' => $this->whenLoaded('tipoCliente', fn() => [
                'tpc_id'     => $this->tipoCliente->tpc_id,
                'tpc_descri' => $this->tipoCliente->tpc_descri,
            ]),

            'contactos' => $this->persona->contactos->map(fn($c) => [
                'con_id'    => $c->con_id,
                'con_tipo'  => $c->con_tipo,
                'con_valor' => $c->con_valor,
            ])->values(),
        ];
    }
}