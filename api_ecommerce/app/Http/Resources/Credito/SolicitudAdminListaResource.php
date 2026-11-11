<?php

namespace App\Http\Resources\Credito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila de la bandeja de solicitudes.
 *
 * Plano y liviano a proposito: la bandeja pagina de a 20 y no necesita
 * el detalle ni las referencias. El Resource del detalle es otro.
 *
 * No devuelve colores ni clases CSS, igual que EstadoSolicitudResource:
 * el Resource entrega el dato, el componente decide como pintarlo.
 */
class SolicitudAdminListaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'scr_id' => $this->scr_id,
            'numero' => $this->scr_numero,

            'fecha'          => $this->scr_creado?->format('d/m/Y'),
            'fecha_completa' => $this->scr_creado?->format('d/m/Y H:i'),

            'cliente' => [
                'nombre' => $this->nombreCompleto(),
                'cedula' => $this->scr_cedula,
            ],

            'total_contado' => (float) $this->scr_toncon,
            'total'         => (float) $this->scr_total,
            'plazo'         => (int) $this->scr_plazo,

            'estado' => [
                'codigo'      => $this->estado?->esc_codigo,
                'descripcion' => $this->estado?->esc_descri,
            ],
        ];
    }
}