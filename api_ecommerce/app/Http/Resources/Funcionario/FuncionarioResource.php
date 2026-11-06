<?php

namespace App\Http\Resources\Funcionario;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FuncionarioResource extends JsonResource
{
    /** fun_fecing / fun_fecegr llegan como texto AAAA-MM-DD (sin cast en el modelo). */
    private function fecha($valor): ?string
    {
        return $valor ? substr((string) $valor, 0, 10) : null;
    }

    /**
     * Cuenta de acceso al panel (nunca se devuelve la contraseña). Una cuenta de
     * Cliente de la tienda no cuenta como acceso del funcionario.
     */
    private function acceso(): ?array
    {
        $u = $this->persona->relationLoaded('usuario') ? $this->persona->usuario : null;

        if (!$u || $u->rol?->rol_descri === 'Cliente') {
            return null;
        }

        return ['usu_id' => $u->usu_id, 'email' => $u->usu_login, 'estado' => $u->usu_estado];
    }

    public function toArray(Request $request): array
    {
        return [
            'fun_id'     => $this->fun_id,
            'fun_fecing' => $this->fecha($this->fun_fecing),
            'fun_fecegr' => $this->fecha($this->fun_fecegr),
            'fun_estado' => (int) $this->fun_estado,
			'rol_id'     => $this->fun_rolid,
            'rol'        => $this->whenLoaded('rol', fn() => $this->rol?->rol_descri),

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

            'cargo' => $this->whenLoaded('cargo', fn() => [
                'crg_id'    => $this->cargo->crg_id,
                'crg_descr' => $this->cargo->crg_descr,
            ]),

            // Nullable: los funcionarios anteriores a esta columna pueden no tener sucursal.
            'sucursal' => $this->whenLoaded('sucursal', fn() => $this->sucursal ? [
                'suc_id'     => $this->sucursal->suc_id,
                'suc_nombre' => $this->sucursal->suc_nombre,
            ] : null),

            'usuario' => $this->acceso(),

            'contactos' => $this->persona->contactos->map(fn($c) => [
                'con_id'    => $c->con_id,
                'con_tipo'  => $c->con_tipo,
                'con_valor' => $c->con_valor,
            ])->values(),
        ];
    }
}
