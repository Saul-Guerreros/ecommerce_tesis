<?php

namespace App\Http\Resources\product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'prd_id'      => $this->prd_id,
            'prd_marid'   => $this->prd_marid,
            'prd_tivid'   => $this->prd_tivid,
            'prd_umdid'   => $this->prd_umdid,
            'prd_codinv'  => $this->prd_codinv,
            'prd_prevta'  => $this->prd_prevta,
            'prd_margen'  => $this->prd_margen,
            'prd_descri'  => $this->prd_descri,
            'prd_stkmin'  => $this->prd_stkmin,
            'prd_afestk'  => $this->prd_afestk,
            'prd_estado'  => $this->prd_estado,
            'prd_creado'  => $this->prd_creado,

            'mar_descri' => $this->whenLoaded('marca', fn () => $this->marca->mar_descri),
            'tiv_descri' => $this->whenLoaded('tipoIva', fn () => $this->tipoIva->tiv_descri),
            'tiv_tasa'   => $this->whenLoaded('tipoIva', fn () => $this->tipoIva->tiv_tasa),
            'umd_codigo' => $this->whenLoaded('unidadMedida', fn () => $this->unidadMedida->umd_codigo),
            'umd_nombre' => $this->whenLoaded('unidadMedida', fn () => $this->unidadMedida->umd_nombre),

            /**
             * stock_actual y precio_sugerido llegan como atributos dinamicos
             * calculados en el controlador (ver ProductoController::conStock()),
             * no como relaciones Eloquent directas: son agregados sobre
             * TODOS los depositos del producto, no un valor de una sola fila.
             */
            'stock_actual'    => $this->whenNotNull($this->stock_actual),
            'precio_sugerido' => $this->whenNotNull($this->precio_sugerido),
        ];
    }
}