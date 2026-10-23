<?php

namespace App\Http\Resources\Servicio;

use App\Models\Servicio\EstadoPresupuesto;
use App\Models\Servicio\PresupuestoServicio;
use App\Services\Servicio\PresupuestoPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Paso 2 - Resumen del presupuesto vigente para el panel (A3 y detalle): estado, total,
 * vencimiento, cómo respondió el cliente y qué acciones admite.
 *
 * @property PresupuestoServicio $resource
 */
class PresupuestoResumenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $codigo  = $this->estado?->epr_codigo;
        $vencido = $this->pse_fecven && $this->pse_fecven->lt(today());

        return [
            'pse_id'     => $this->pse_id,
            'numero'     => $this->pse_numero,
            'versio'     => $this->pse_versio,
            'codigo'     => $codigo,
            'estado'     => $this->estado?->epr_descri,
            'total'      => (float) $this->pse_total,
            'fecven'     => $this->pse_fecven?->format('Y-m-d'),
            'vencido'    => $vencido,
            'respuesta'  => $this->pse_respon ? [
                'fecha'      => $this->pse_respon->format('Y-m-d H:i'),
                'canal'      => $this->pse_canres,
                'etiqueta'   => PresupuestoServicio::ETIQUETAS_RESPUESTA[$this->pse_canres] ?? null,
                'registro'   => $this->relationLoaded('respondidoPor')
                    ? ($this->respondidoPor?->persona?->nombre_completo ?? $this->respondidoPor?->usu_login) : null,
                'obs'        => $this->pse_obsres,
                'motivo'     => $this->pse_motrec,
            ] : null,
            'puede_responder' => $codigo === EstadoPresupuesto::ENVIADO && !$vencido,
            'puede_imprimir'  => in_array($codigo, PresupuestoPdfService::IMPRIMIBLES, true),
        ];
    }
}
