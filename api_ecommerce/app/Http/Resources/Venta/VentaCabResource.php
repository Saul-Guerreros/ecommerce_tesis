<?php

namespace App\Http\Resources\Venta;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VentaCabResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ven_id'     => $this->ven_id,
            'ven_pedid'  => $this->ven_pedid,
            'ven_cliid'  => $this->ven_cliid,
            'ven_sucid'  => $this->ven_sucid,

            // Documento
            'ven_nrodoc'    => $this->ven_nrodoc,   // 001-001-0000123, derivado del modelo
            'ven_establec'  => $this->ven_establec,
            'ven_puntoexp'  => $this->ven_puntoexp,
            'ven_numero'    => $this->ven_numero,
            'ven_timbrado'  => $this->ven_timbrado,
            'ven_timb_inicio' => $this->ven_timb_inicio?->format('Y-m-d'),
            'ven_timb_venc'   => $this->ven_timb_venc?->format('Y-m-d'),
            'ven_emp_ruc'     => $this->ven_emp_ruc,
            'ven_emp_razon'   => $this->ven_emp_razon,
            'ven_fchaem'    => $this->ven_fchaem?->format('Y-m-d'),

            // Adquirente (snapshot)
            'ven_razonsocial' => $this->ven_razonsocial,
            'ven_ruc'         => $this->ven_ruc,
            'ven_ci'          => $this->ven_ci,
            'ven_direccion'   => $this->ven_direccion,
            'ven_telefono'    => $this->ven_telefono,
            'ven_email'       => $this->ven_email,

            'ven_condicion'   => $this->ven_condicion,
            'ven_cant_cuotas' => $this->ven_cant_cuotas,

            // Valor de venta y liquidacion de IVA
            'ven_exenta'   => $this->ven_exenta,
            'ven_grav5'    => $this->ven_grav5,
            'ven_grav10'   => $this->ven_grav10,
            'ven_subtotal' => $this->ven_subtotal,
            'ven_total'    => $this->ven_total,
            'ven_iva5'     => $this->ven_iva5,
            'ven_iva10'    => $this->ven_iva10,
            'ven_totiva'   => $this->ven_totiva,

            // Control
            'ven_estado'  => $this->ven_estado,
            'ven_obs'     => $this->ven_obs,
            'ven_creado'  => $this->ven_creado,
            'ven_anulado' => $this->ven_anulado,
            'ven_motanu'  => $this->ven_motanu,

            'suc_nombre' => $this->whenLoaded('sucursal', fn () => $this->sucursal->suc_nombre),
            'caj_descri' => $this->whenLoaded('caja', fn () => $this->caja->caj_descri),
            'ped_numero' => $this->whenLoaded('pedido', fn () => $this->pedido->ped_numero),

            'detalle' => VentaDetalleResource::collection($this->whenLoaded('detalle')),
        ];
    }
}