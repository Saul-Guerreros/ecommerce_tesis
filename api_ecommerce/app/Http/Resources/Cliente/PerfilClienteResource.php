<?php

namespace App\Http\Resources\Cliente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerfilClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $persona = $this->persona;
        $cliente = $persona?->cliente;
        $dirPredet = $persona?->direcciones?->firstWhere('dir_predet', 1);

        return [
            'usuario_id' => $this->usu_id,
            'email'      => $this->usu_login,
            'estado'     => $this->usu_estado,

            'persona' => [
                'per_id'          => $persona?->per_id,
                'nombre_completo' => $persona?->nombre_completo,
                'per_nombr1'      => $persona?->per_nombr1,
                'per_nombr2'      => $persona?->per_nombr2,
                'per_apell1'      => $persona?->per_apell1,
                'per_apell2'      => $persona?->per_apell2,
                'per_cedula'      => $persona?->per_cedula,
                'tipo_documento'  => $persona?->tipoDocumento?->tpd_descri,
                'per_fechna'      => $persona?->per_fechna?->format('Y-m-d'),
                'avatar_url'      => $persona?->per_avatar
                    ? rtrim(env('APP_URL'), '/') . '/storage/' . $persona->per_avatar
                    : null,
            ],

            'cliente' => $cliente ? [
                'cli_id'       => $cliente->cli_id,
                'cli_ruc'      => $cliente->cli_ruc,
                'tipo_cliente' => $cliente->tipoCliente?->tpc_descri,
            ] : null,

            'celular' => $persona?->contactos?->firstWhere('con_tipo', 'CELULAR')?->con_valor,

            'direccion_predeterminada' => $dirPredet ? [
                'dir_id'      => $dirPredet->dir_id,
                'barrio'      => $dirPredet->barrio?->bar_descri,
                'ciudad'      => $dirPredet->barrio?->ciudad?->ciu_descri,
                'calle'       => $dirPredet->calle?->cal_descri,
                'dir_nrocasa' => $dirPredet->dir_nrocasa,
            ] : null,
        ];
    }
}