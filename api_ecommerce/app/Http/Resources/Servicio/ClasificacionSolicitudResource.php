<?php

namespace App\Http\Resources\Servicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Punto 7 - Clasificación de una solicitud (lista y detalle del admin). */
class ClasificacionSolicitudResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cls_id'      => $this->cls_id,
            'estado'      => $this->cls_estado,
            'dispositivo' => $this->tipoDispositivo
                ? ['id' => $this->tipoDispositivo->tdi_id, 'descri' => $this->tipoDispositivo->tdi_descri] : null,
            'categoria'   => $this->categoria
                ? ['id' => $this->categoria->caf_id, 'codigo' => $this->categoria->caf_codigo, 'descri' => $this->categoria->caf_descri] : null,
            'urgencia'    => $this->urgencia
                ? ['id' => $this->urgencia->urg_id, 'codigo' => $this->urgencia->urg_codigo,
                   'descri' => $this->urgencia->urg_descri, 'color' => $this->urgencia->urg_color] : null,
            'confianza'   => $this->cls_confia,
            'motivo'      => $this->cls_motivo,
            'dispositivo_inconsistente' => $this->dispositivoInconsistente(),
            'revisada'    => $this->cls_revisa !== null,
            // Solo en el detalle (relaciones cargadas a pedido)
            'dispositivo_cliente' => $this->whenLoaded('tipoDispositivoCliente', fn () => $this->tipoDispositivoCliente?->tdi_descri),
            'dispositivo_ia'      => $this->whenLoaded('tipoDispositivoIa', fn () => $this->tipoDispositivoIa?->tdi_descri),
            'falla_ia'            => $this->whenLoaded('fallaIa', fn () => $this->fallaIa?->fll_descri),
            'correcciones'        => $this->whenLoaded('correcciones', fn () => $this->correcciones->map(fn ($c) => [
                'campo'    => $c->ccr_campo,
                'anterior' => $c->ccr_valant,
                'nuevo'    => $c->ccr_valnue,
                'motivo'   => $c->ccr_motivo,
                'usuario'  => $c->usuario?->persona?->nombre_completo ?? $c->usuario?->usu_login,
                'fecha'    => $c->ccr_fecha?->format('Y-m-d H:i'),
            ])->values()),
            'latencia_ms' => $this->cls_latms,
            'version'     => $this->cls_prmver,
            'actualizado' => $this->cls_actual?->format('Y-m-d H:i'),
        ];
    }
}