<?php
namespace App\Http\Resources\Compra;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LibroCompraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'lcm_id'     => $this->lcm_id,
            'lcm_tipo'   => $this->lcm_tipo,   // FC | NC
            'lcm_comid'  => $this->lcm_comid,
            'lcm_ncrid'  => $this->lcm_ncrid,
            'lcm_estado' => $this->lcm_estado, // 1 Vigente, 2 Anulado
            'lcm_fecha'  => $this->lcm_fecha?->format('Y-m-d'),
            'lcm_nrodoc' => $this->lcm_nrodoc,
            'lcm_proraz' => $this->lcm_proraz,
            'lcm_proruc' => $this->lcm_proruc,
            'lcm_exenta' => $this->lcm_exenta,
            'lcm_grav5'  => $this->lcm_grav5,
            'lcm_grav10' => $this->lcm_grav10,
            'lcm_iva5'   => $this->lcm_iva5,
            'lcm_iva10'  => $this->lcm_iva10,
            'lcm_total'  => $this->lcm_total,
        ];
    }
}