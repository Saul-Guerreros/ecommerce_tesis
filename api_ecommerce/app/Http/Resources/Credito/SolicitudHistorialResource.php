<?php

namespace App\Http\Resources\Credito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Linea de la bitacora: "14/10 10:02  EN EVALUACION - admin".
 *
 * El autor se resuelve acá y no en el componente: si el usuario ya no
 * tiene persona asociada, se cae al login en vez de mostrar un hueco.
 */
class SolicitudHistorialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sch_id' => $this->sch_id,
            'fecha'  => $this->sch_fecha?->format('d/m/Y H:i'),

            'estado_anterior' => $this->estadoAnterior?->esc_descri,
            'estado_nuevo'    => $this->estadoNuevo?->esc_descri,
            'codigo_nuevo'    => $this->estadoNuevo?->esc_codigo,

            'autor' => $this->sch_usuid
                ? ($this->usuario?->persona?->nombre_completo ?: $this->usuario?->usu_login)
                : 'Sistema',

            'observacion' => $this->sch_observ,
        ];
    }
}