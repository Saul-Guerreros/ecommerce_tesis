<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipoDocumentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tpd_id'     => $this->tpd_id,
            'tpd_codigo' => $this->tpd_codigo,
            'tpd_descri' => $this->tpd_descri,
            'tpd_estado' => (int) $this->tpd_estado,
            'tpd_creado' => $this->tpd_creado,
            // true = lo usa el sistema por código (CI, RUC): protegido
            'protegido'  => $this->esDelSistema(),
            'usos_count' => $this->whenNotNull($this->usos_count),
        ];
    }
}
