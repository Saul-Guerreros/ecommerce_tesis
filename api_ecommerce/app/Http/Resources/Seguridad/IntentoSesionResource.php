<?php

namespace App\Http\Resources\Seguridad;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IntentoSesionResource extends JsonResource
{
    use Concerns;

    public function toArray(Request $request): array
    {
        return [
            'ise_id'     => $this->ise_id,
            // Lo que se escribió en el login (puede no corresponder a un usuario real)
            'ise_logusa' => $this->ise_logusa,
            'exitoso'    => (int) $this->ise_result === 1,
            'ise_obs'    => $this->ucfirst($this->ise_obs),
            'ise_iporig' => $this->ise_iporig,
            'ise_userag' => $this->ise_userag,
            'ise_creado' => $this->iso($this->ise_creado),
            'usuario'    => $this->datosUsuario($this->usuario),
        ];
    }
}
