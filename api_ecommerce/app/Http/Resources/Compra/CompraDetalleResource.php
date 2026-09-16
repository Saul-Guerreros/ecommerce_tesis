<?php

namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompraDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cmd_id'      => $this->cmd_id,
            'cmd_prdid'   => $this->cmd_prdid,
            'cmd_umdid'   => $this->cmd_umdid,
            'cmd_factconv'=> $this->cmd_factconv,
            'cmd_tivid'   => $this->cmd_tivid,
            'cmd_nroprd'  => $this->cmd_nroprd,
            'cmd_descri'  => $this->cmd_descri,
            'cmd_costo'   => $this->cmd_costo,
            'cmd_precun'  => $this->cmd_precun,
            'cmd_despor'  => $this->cmd_despor,
            'cmd_descto'  => $this->cmd_descto,
            'cmd_tasa'    => $this->cmd_tasa,
            'cmd_gravada' => $this->cmd_gravada,
            'cmd_iva'     => $this->cmd_iva,
            'cmd_canfac'  => $this->cmd_canfac,
            'cmd_canrec'  => $this->cmd_canrec,

            'prd_codinv' => $this->whenLoaded('producto', fn () => $this->producto->prd_codinv),
            'tiv_descri' => $this->whenLoaded('tipoIva', fn () => $this->tipoIva->tiv_descri),
            'umd_codigo' => $this->whenLoaded('unidadMedida', fn () => $this->unidadMedida->umd_codigo),        
        ];
    }
}