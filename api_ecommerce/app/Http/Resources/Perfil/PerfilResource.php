<?php

namespace App\Http\Resources\Perfil;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerfilResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $persona = $this->whenLoaded('persona');

        return [
            'usuario_id' => $this->usu_id,
            'email'      => $this->usu_login,
            'estado'     => $this->usu_estado,
            'rol'        => $this->whenLoaded('rol', fn () => $this->rol?->rol_descri),
            'permisos'   => $this->whenLoaded('rol', fn () =>
                $this->rol && $this->rol->relationLoaded('permisos')
                    ? $this->rol->permisos->pluck('prm_descri')->values()
                    : []
            ),

            'persona' => $this->whenLoaded('persona', fn () => [
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
                    ? rtrim(config('app.url'), '/') . '/storage/' . $persona->per_avatar
                    : null,
            ]),

            'celular' => $this->whenLoaded('persona', fn () =>
                $persona?->contactos?->firstWhere('con_tipo', 'CELULAR')?->con_valor
            ),
        ];
    }
}