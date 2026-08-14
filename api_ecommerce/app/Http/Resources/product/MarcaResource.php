<?php

namespace App\Http\Resources\product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarcaResource extends JsonResource
{
     public function toArray(Request $request): array
    {
        return [
            'mar_id'     => $this->mar_id,
            'mar_descri' => $this->mar_descri,
        ];
    }
}
