<?php

namespace App\Http\Resources\Seguridad;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistorialPasswordResource extends JsonResource
{
    use Concerns;

    // Deliberadamente NO se expone hpa_pasant (hash de la contraseña anterior).
    public function toArray(Request $request): array
    {
        return [
            'hpa_id'     => $this->hpa_id,
            'hpa_iporig' => $this->hpa_iporig,
            'hpa_creado' => $this->iso($this->hpa_creado),
            'usuario'    => $this->datosUsuario($this->usuario),
        ];
    }
}
