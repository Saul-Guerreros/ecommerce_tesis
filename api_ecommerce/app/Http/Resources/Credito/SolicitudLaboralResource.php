<?php

namespace App\Http\Resources\Credito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bloque de datos laborales del BORRADOR
 * (solicitud_temporal.stm_labora). Arreglo, no modelo.
 *
 * antiguedad es el texto ya armado por el servicio ("2 años 4 meses").
 * Se calcula en el backend aunque sea presentacion, por una razon
 * concreta: el mismo texto tiene que aparecer en el formulario, en el
 * acordeon de Confirmacion y despues en el panel del administrador.
 * Calcularlo en cada uno seria escribir tres veces la misma regla.
 */
class SolicitudLaboralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $l = $this->resource;

        return [
            'scr_empres' => $l['scr_empres'] ?? null,
            'scr_dirlab' => $l['scr_dirlab'] ?? null,
            'scr_tellab' => $l['scr_tellab'] ?? null,
            'scr_cargo'  => $l['scr_cargo']  ?? null,
            'scr_ingres' => isset($l['scr_ingres']) ? (float) $l['scr_ingres'] : null,
            'scr_fecing' => $l['scr_fecing'] ?? null,

            'antiguedad' => $l['antiguedad'] ?? null,
            'resumen'    => $l['resumen']    ?? null,
        ];
    }
}