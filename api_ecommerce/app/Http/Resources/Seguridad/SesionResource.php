<?php

namespace App\Http\Resources\Seguridad;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SesionResource extends JsonResource
{
    use Concerns;

    // Deliberadamente NO se expone ses_tokhas (hash del JWT).
    public function toArray(Request $request): array
    {
        $estado = match (true) {
            (int) $this->ses_revoca === 1 => 'REVOCADA',
            $this->ses_fecexp->isPast()   => 'EXPIRADA',
            default                       => 'ACTIVA',
        };

        return [
            'ses_id'     => $this->ses_id,
            'ses_iporig' => $this->ses_iporig,
            'ses_userag' => $this->ses_userag,
            'ses_creado' => $this->iso($this->ses_creado),
            'ses_fecexp' => $this->iso($this->ses_fecexp),
            'estado'     => $estado,
            'usuario'    => $this->datosUsuario($this->usuario),
        ];
    }
}
