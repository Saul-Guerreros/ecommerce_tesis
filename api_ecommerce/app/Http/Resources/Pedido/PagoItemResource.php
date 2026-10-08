<?php

namespace App\Http\Resources\Pedido;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un pago del borrador (pedido_temporal.ptm_pago.pagos[]).
 *
 * La ruta del archivo nunca sale al cliente: solo se informa si el pago ya
 * tiene comprobante, que es lo que el Paso 2 necesita para mostrar
 * "Comprobante ya cargado" y para decidir si envia conservar = 1.
 */
class PagoItemResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $p = $this->resource;

        return [
            'orden'             => (int) ($p['orden'] ?? 0),
            'mpa_codigo'        => $p['mpa_codigo'],
            'mpa_descri'        => $p['mpa_descri'] ?? '',
            'monto'             => $p['monto'] !== null ? (float) $p['monto'] : null,
            'tiene_comprobante' => !empty($p['comprobante_temp']),
        ];
    }
}