<?php

namespace App\Http\Resources\product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategorieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cat_id'     => $this->cat_id,
            'cat_idpadr' => $this->cat_idpadr,
            'cat_nivel'  => $this->cat_nivel,
            'cat_nombre' => $this->cat_nombre,
            'padre'      => $this->whenLoaded('padre', function () {
                return [
                    'cat_id'     => $this->padre->cat_id,
                    'cat_nombre' => $this->padre->cat_nombre,
                ];
            }),
        ];
    }
}
