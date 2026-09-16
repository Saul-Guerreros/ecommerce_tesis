<?php

namespace App\Http\Resources\product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConfiguracionPrecioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cpr_id'        => $this->cpr_id,
            'cpr_marmin'    => $this->cpr_marmin,
            'cpr_marmaj'    => $this->cpr_marmaj,
            'cpr_canminmaj' => $this->cpr_canminmaj,
        ];
    }
}