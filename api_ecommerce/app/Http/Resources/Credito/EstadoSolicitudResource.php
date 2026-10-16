<?php

namespace App\Http\Resources\Credito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Estado de la solicitud.
 *
 * Deliberadamente NO devuelve colores ni clases CSS, igual que
 * PedidoListaResource: eso es presentacion y vive en el componente.
 * El Resource entrega el codigo (dato) y la descripcion (texto); la
 * pantalla decide como pintarlo.
 */
class EstadoSolicitudResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'esc_id'     => $this->esc_id,
            'esc_codigo' => $this->esc_codigo,
            'esc_descri' => $this->esc_descri,
            'esc_orden'  => (int) $this->esc_orden,
        ];
    }
}