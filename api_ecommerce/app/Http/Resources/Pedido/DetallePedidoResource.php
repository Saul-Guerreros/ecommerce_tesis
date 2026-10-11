<?php

namespace App\Http\Resources\Pedido;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una línea de la tabla PRODUCTOS.
 *
 * dpd_descri es un snapshot del nombre tomado al confirmar el pedido:
 * se muestra ese y no producto.prd_descri, para que renombrar un
 * producto en el catálogo no reescriba pedidos ya cerrados.
 *
 * La tasa de IVA sí se lee de la relación producto.tipoIva porque
 * detalle_pedido guarda el monto del IVA (dpd_iva) pero no el
 * porcentaje, y la pantalla necesita mostrar la columna «IVA %».
 */
class DetallePedidoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'dpd_id'     => $this->dpd_id,
            'prd_id'     => $this->dpd_prdid,
            'descripcion' => $this->dpd_descri,
            'cantidad'   => (float) $this->dpd_cantid,
            'precio'     => (float) $this->dpd_precio,

            'iva_tasa'   => (float) ($this->producto?->tipoIva?->tiv_tasa ?? 0),
            'iva_monto'  => (float) $this->dpd_iva,

            'subtotal'   => (float) $this->dpd_subtot,
            'total'      => (float) $this->dpd_total,

            // Esta pantalla sólo gestiona la modalidad contado; el crédito
            // tiene su propio flujo de cuotas.
            'modalidad'  => $this->dpd_modpag,
        ];
    }
}