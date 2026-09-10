<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $funcionario = $this->persona?->funcionario;

        return [
            'usuario_id' => $this->usu_id,
            'email' => $this->usu_login,
            'estado' => $this->usu_estado,
            'rol_id' => $this->usu_rolid,
            'rol' => $this->rol?->rol_descri,
            'creado' => $this->usu_creado,
            'persona' => [
                'persona_id' => $this->persona?->per_id,
                'nombre_completo' => $this->persona?->nombre_completo,
                'nombre1' => $this->persona?->per_nombr1,
                'nombre2' => $this->persona?->per_nombr2,
                'apellido1' => $this->persona?->per_apell1,
                'apellido2' => $this->persona?->per_apell2,
                'cedula' => $this->persona?->per_cedula,
                'fecha_nacimiento' => $this->persona?->per_fechna,
            ],
            'cargo_id' => $funcionario?->fun_crgid,
            'cargo' => $funcionario?->cargo?->crg_descr,
            'fecha_ingreso' => $funcionario?->fun_fecing,

            // Sucursales asignadas, con cual es la principal.
            'sucursales' => $this->whenLoaded('sucursales', fn () =>
                $this->sucursales->map(fn ($s) => [
                    'suc_id'     => $s->suc_id,
                    'suc_nombre' => $s->suc_nombre,
                    'principal'  => (int) $s->pivot->uss_princi === 1,
                ])
            ),
        ];
    }
}