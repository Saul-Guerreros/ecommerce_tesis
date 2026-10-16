<?php

namespace App\Http\Resources\Credito;

use App\Models\Credito\SolicitudCreditoReferencia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Unico Resource para las dos formas en que viven las referencias:
 * arreglo dentro del borrador (solicitud_temporal.stm_refere) y modelo
 * Eloquent una vez generada la solicitud.
 *
 * Se resuelve con el helper valor() en vez de duplicar la clase, porque
 * los campos son exactamente los mismos y el frontend consume la misma
 * estructura en las dos pantallas (Paso 3 y Confirmacion). Dos clases
 * identicas se desincronizan en el primer cambio.
 */
class SolicitudReferenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'scf_orden'  => (int) ($this->valor('scf_orden') ?? 0),
            'scf_nombre' => $this->valor('scf_nombre'),
            'scf_celula' => $this->valor('scf_celula'),
            'scf_relaci' => $this->valor('scf_relaci'),

            // Etiqueta legible resuelta en un solo lugar del sistema.
            'relacion' => SolicitudCreditoReferencia::etiquetaRelacion($this->valor('scf_relaci')),
        ];
    }

    private function valor(string $campo): mixed
    {
        if (is_array($this->resource)) {
            return $this->resource[$campo] ?? null;
        }

        return $this->resource->{$campo} ?? null;
    }
}