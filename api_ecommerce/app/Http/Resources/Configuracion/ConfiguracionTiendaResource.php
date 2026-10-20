<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConfiguracionTiendaResource extends JsonResource
{
    public function toArray(Request $request): array
{
    return [
        'ctn_id'            => $this->ctn_id,
        'ctn_pocstk_activo' => $this->ctn_pocstk_activo,
        'ctn_pocstk_umbral' => $this->ctn_pocstk_umbral,
        'ctn_pago_banco'            => $this->ctn_pago_banco,
        'ctn_pago_banco_titular'    => $this->ctn_pago_banco_titular,
        'ctn_pago_banco_cuenta'     => $this->ctn_pago_banco_cuenta,
        'ctn_pago_tigo_titular'     => $this->ctn_pago_tigo_titular,
        'ctn_pago_tigo_numero'      => $this->ctn_pago_tigo_numero,
        'ctn_pago_tigo_referencia'  => $this->ctn_pago_tigo_referencia,
        'ctn_pago_efectivo_mensaje' => $this->ctn_pago_efectivo_mensaje,
        'ctn_retactivo'             => $this->ctn_retactivo,
        'ctn_retdias'               => $this->ctn_retdias,
        'ctn_retaviso'              => $this->ctn_retaviso,
    ];
}
}