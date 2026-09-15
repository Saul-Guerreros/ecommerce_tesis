<?php

namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipoAjusteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tpa_id'     => $this->tpa_id,
            'tpa_codig'  => $this->tpa_codig,
            'tpa_descri' => $this->tpa_descri,
            'tpa_natura' => $this->tpa_natura,
            'tpa_reqcom' => $this->tpa_reqcom,
            'tpa_estado' => $this->tpa_estado,
            'usos_count' => $this->whenNotNull($this->usos_count),
        ];
    }
}