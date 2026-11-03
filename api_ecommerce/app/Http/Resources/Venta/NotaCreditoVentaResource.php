<?php

namespace App\Http\Resources\Venta;

use App\Http\Resources\Compra\MotivoNotaCreditoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotaCreditoVentaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ncv_id'    => $this->ncv_id,
            'ncv_venid' => $this->ncv_venid,
            'ncv_cliid' => $this->ncv_cliid,
            'ncv_sucid' => $this->ncv_sucid,
            'ncv_depid' => $this->ncv_depid,

            // Documento
            'ncv_nrodoc'      => $this->ncv_nrodoc,   // 001-001-0000045
            'ncv_establec'    => $this->ncv_establec,
            'ncv_puntoexp'    => $this->ncv_puntoexp,
            'ncv_numero'      => $this->ncv_numero,
            'ncv_timbrado'    => $this->ncv_timbrado,
            'ncv_timb_inicio' => $this->ncv_timb_inicio?->format('Y-m-d'),
            'ncv_timb_venc'   => $this->ncv_timb_venc?->format('Y-m-d'),
            'ncv_emp_ruc'     => $this->ncv_emp_ruc,
            'ncv_emp_razon'   => $this->ncv_emp_razon,
            'ncv_fecha'       => $this->ncv_fecha?->format('Y-m-d'),

            // Adquirente (copiado de la factura)
            'ncv_razonsocial' => $this->ncv_razonsocial,
            'ncv_ruc'         => $this->ncv_ruc,
            'ncv_ci'          => $this->ncv_ci,

            // Motivo
            'ncv_mncid' => $this->ncv_mncid,
            'motivo'    => new MotivoNotaCreditoResource($this->whenLoaded('motivo')),

            // Importes
            'ncv_exenta'   => $this->ncv_exenta,
            'ncv_grav5'    => $this->ncv_grav5,
            'ncv_grav10'   => $this->ncv_grav10,
            'ncv_subtotal' => $this->ncv_subtotal,
            'ncv_total'    => $this->ncv_total,
            'ncv_iva5'     => $this->ncv_iva5,
            'ncv_iva10'    => $this->ncv_iva10,
            'ncv_totiva'   => $this->ncv_totiva,

            // Control
            'ncv_estado'  => $this->ncv_estado,
            'ncv_obs'     => $this->ncv_obs,
            'ncv_creado'  => $this->ncv_creado,
            'ncv_anulado' => $this->ncv_anulado,
            'ncv_motanu'  => $this->ncv_motanu,

            // Documento asociado: lo que la DNIT pide como "asociado".
            'factura' => $this->whenLoaded('factura', fn () => [
                'ven_id'     => $this->factura->ven_id,
                'ven_nrodoc' => $this->factura->ven_nrodoc,
                'ven_fchaem' => $this->factura->ven_fchaem?->format('Y-m-d'),
                'ven_total'  => $this->factura->ven_total,
            ]),

            'suc_nombre' => $this->whenLoaded('sucursal', fn () => $this->sucursal->suc_nombre),
            'dpo_descri' => $this->whenLoaded('deposito', fn () => $this->deposito?->dpo_descri),
            'dpo_nombre' => $this->whenLoaded('deposito', fn () => $this->deposito?->dpo_nombre),

            // Declaración electrónica. null = la NC se emitió sin ella
            // (FACTURACION_ACTIVA=false). No se exponen las rutas de los
            // archivos: solo si existen.
            'fe' => $this->whenLoaded('fe', fn ($fe) => $fe ? [
                'estado'            => $fe->nfe_estado,   // 1 Pendiente, 2 Enviada, 3 Rechazada
                'error'             => $fe->nfe_error,
                'mensaje'           => $fe->nfe_mensaje,
                'aviso'             => $fe->nfe_aviso,
                'cdc'               => $fe->nfe_cdc,
                'ambiente'          => $fe->nfe_ambiente,
                'qr'                => $fe->nfe_qr,
                'aprobado'          => $fe->nfe_aprobado?->format('Y-m-d H:i:s'),
                'intentos'          => $fe->nfe_intentos,
                'ultimo_intento'    => $fe->nfe_ultimo_intento?->format('Y-m-d H:i:s'),
                'tiene_xml'         => !empty($fe->nfe_xml_ruta),
                'tiene_pdf'         => !empty($fe->nfe_pdf_ruta),
                'bloquea_anulacion' => $fe->bloqueaAnulacion(),
                'correo_enviado'    => $fe->nfe_correo_enviado,
                'correo_detalle'    => $fe->nfe_correo_detalle,
            ] : null),

            'detalle' => DetNotaCreditoVentaResource::collection($this->whenLoaded('detalle')),
        ];
    }
}