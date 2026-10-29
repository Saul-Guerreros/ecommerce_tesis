<?php

namespace App\Http\Resources\Servicio;

use App\Models\Servicio\DiagnosticoPreliminar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiagnosticoPreliminarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $conEstimacion = !$this->dpr_garant && $this->dpr_cosmin !== null;

        return [
            'dpr_id'     => $this->dpr_id,
            'disponible' => $this->dpr_estado === DiagnosticoPreliminar::GENERADO,
            'garantia'   => $this->dpr_garant,
            'resumen'    => $this->dpr_resume,
            'confianza'  => $this->dpr_confia,
            'preguntas'  => $this->dpr_pregun ?? [],
            'fallas'     => $this->fallas->map(fn ($f) => [
                'fll_id'       => $f->dpf_fllid,
                'descripcion'  => $f->falla?->fll_descri,
                'probabilidad' => $f->dpf_probab,
            ])->values(),
            'trabajos'   => $this->trabajos->map(fn ($t) => [
                'trb_id'      => $t->dpt_trbid,
                'descripcion' => $t->trabajo?->trb_descri,
                'precio'      => $this->dpr_garant || $t->dpt_precio === null ? null : (float) $t->dpt_precio,
            ])->values(),
            'estimacion' => $conEstimacion ? [
                'costo_min' => (float) $this->dpr_cosmin,
                'costo_max' => (float) $this->dpr_cosmax,
                'dias'      => $this->dpr_dias,
            ] : null,
            'coincidencia' => $this->dpr_coinci,
             // Punto 7: tipo usado; 'detectado' = lo determinó la IA porque el cliente no lo eligió.
            'tipo_dispositivo' => [
                'tdi_id'     => $this->dpr_tdiid,
                'tdi_descri' => $this->tipoDispositivo?->tdi_descri,
                'detectado'  => (bool) ($this->tdi_detectado ?? false),
            ],
            'creado'       => $this->dpr_creado?->format('Y-m-d H:i'),
        ];
    }
}