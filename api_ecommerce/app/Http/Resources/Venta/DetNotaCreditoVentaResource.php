<?php

namespace App\Http\Resources\Venta;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetNotaCreditoVentaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'dnv_id'          => $this->dnv_id,
            'dnv_vndid'       => $this->dnv_vndid,
            'dnv_prdid'       => $this->dnv_prdid,
            'dnv_nroitem'     => $this->dnv_nroitem,
            'dnv_codigo'      => $this->dnv_codigo,
            'dnv_descripcion' => $this->dnv_descripcion,
            'dnv_umedida'     => $this->dnv_umedida,
            'dnv_cantidad'    => $this->dnv_cantidad,
            'dnv_precio'      => $this->dnv_precio,
            'dnv_tasa'        => $this->dnv_tasa,
            'dnv_valorventa'  => $this->dnv_valorventa,
            'dnv_iva'         => $this->dnv_iva,
            'dnv_canrei'      => $this->dnv_canrei,
        ];
    }
}