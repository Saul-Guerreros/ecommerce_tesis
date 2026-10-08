<?php

namespace App\Http\Resources\Pedido;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetodoPagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'mpa_id'      => $this->mpa_id,
            'mpa_codigo'  => $this->mpa_codigo,
            'mpa_descri'  => $this->mpa_descri,
            'mpa_requcom' => $this->mpa_requcom,
        ];
    }
}