<?php

namespace App\Http\Resources\Cupone;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CuponeResource extends JsonResource
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
            "type_count" => $this->resource->type_count,
            "num_use" => $this->resource->num_use,
            "type_cupone" => $this->resource->type_cupone,
            "created_at" => $this->resource->created_at->format("Y-m-d h:i A"),//"6 AM 6PM"
            "state" => $this->resource->state,
            "products" => $this->resource->products
                ->map(function($product_aux) {
                    // FIX: si el producto referenciado ya no existe (fue
                    // borrado), ->product es null y ->prd_id tronaba con
                    // "Attempt to read property on null", rompiendo TODO
                    // el listado (index). Con "?->" se devuelve null en
                    // vez de excepcion, y luego se filtra abajo.
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