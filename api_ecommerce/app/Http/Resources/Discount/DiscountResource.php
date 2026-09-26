<?php

namespace App\Http\Resources\Discount;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->resource->id,
            "code" => $this->resource->code,
            "type_discount" => $this->resource->type_discount,
            "discount" => $this->resource->discount,
            "start_date" => Carbon::parse($this->resource->start_date)->format("Y-m-d"),
            "end_date" => Carbon::parse($this->resource->end_date)->format("Y-m-d"),
            "discount_type" => $this->resource->discount_type,
            "created_at" => $this->resource->created_at->format("Y-m-d h:i A"),
            "type_campaing" => $this->resource->type_campaing,
            "state" => $this->resource->state,
            "products" => $this->resource->products
                ->map(function($product_aux) {
                    // Null-safe: si el producto referenciado ya no existe,
                    // evita romper todo el listado.
                    if (!$product_aux->product) {
                        return null;
                    }
                    return [
                        "id" => $product_aux->product->prd_id,
                        // prd_titulo se movió a publicacion_web.pub_titulo
                        "title" => $product_aux->product->publicacionWeb?->pub_titulo ?? $product_aux->product->prd_codinv,
                        "id_aux" => $product_aux->id,
                    ];
                })
                ->filter()
                ->values(),
            "categories" => $this->resource->categories
                ->map(function($categorie_aux) {
                    if (!$categorie_aux->categorie) {
                        return null;
                    }
                    return [
                        "id" => $categorie_aux->categorie->cat_id,
                        "name" => $categorie_aux->categorie->cat_nombre,
                        "id_aux" => $categorie_aux->id,
                    ];
                })
                ->filter()
                ->values(),
            "brands" => $this->resource->brands
                ->map(function($brand_aux) {
                    if (!$brand_aux->brand) {
                        return null;
                    }
                    return [
                        "id" => $brand_aux->brand->mar_id,
                        "name" => $brand_aux->brand->mar_descri,
                        "id_aux" => $brand_aux->id,
                    ];
                })
                ->filter()
                ->values(),
        ];
    }
}
