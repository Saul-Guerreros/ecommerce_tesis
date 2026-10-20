<?php

namespace App\Http\Resources\Venta;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VentaDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'vnd_id'          => $this->vnd_id,
            'vnd_prdid'       => $this->vnd_prdid,
            'vnd_nroitem'     => $this->vnd_nroitem,
            'vnd_codigo'      => $this->vnd_codigo,
            'vnd_descripcion' => $this->vnd_descripcion,
            'vnd_umedida'     => $this->vnd_umedida,
            'vnd_nroserie'    => $this->vnd_nroserie,
            'vnd_cantidad'    => $this->vnd_cantidad,
            'vnd_precio'      => $this->vnd_precio,
            'vnd_descuento'   => $this->vnd_descuento,
            'vnd_tasa'        => $this->vnd_tasa,
            'vnd_valorventa'  => $this->vnd_valorventa,
            'vnd_iva'         => $this->vnd_iva,
        ];
    }
}