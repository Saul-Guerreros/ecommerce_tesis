<?php

namespace App\Http\Resources\Servicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FallaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'fll_id'           => $this->fll_id,
            'fll_codigo'       => $this->fll_codigo,
            'fll_descri'       => $this->fll_descri,
            'fll_tdiid'        => $this->fll_tdiid,
            'fll_estado'       => (int) $this->fll_estado,
            'fll_creado'       => $this->fll_creado?->format('Y-m-d H:i'),
            // null = falla genérica (sin tipo); se evita instanciar el Resource con null
            'tipo_dispositivo' => $this->whenLoaded('tipoDispositivo', fn () => $this->tipoDispositivo
                ? new TipoDispositivoResource($this->tipoDispositivo)
                : null),
            'usos_count'       => $this->whenNotNull($this->usos_count),
        ];
    }
}
