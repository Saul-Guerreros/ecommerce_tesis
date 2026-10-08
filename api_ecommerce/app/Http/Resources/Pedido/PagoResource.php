<?php

namespace App\Http\Resources\Pedido;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa el borrador de pago normalizado (PedidoTemporal::pagoNormalizado).
 *
 * mpa_codigo / mpa_descri / tiene_comprobante se mantienen en la raiz por
 * compatibilidad: son los campos que consume el Paso 3 tal como esta hoy.
 * Solo tienen valor cuando modo = "unico"; con pago dividido quedan en null
 * y la informacion real esta en "pagos".
 */
class PagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $p = $this->resource;

        $pagos = collect($p['pagos'] ?? [])
            ->values()
            ->map(fn ($pago, $i) => array_merge($pago, ['orden' => $i + 1]));

        $esUnico = ($p['modo'] ?? null) === 'unico';
        $primero = $pagos->first();

        return [
            'modo'           => $p['modo'] ?? 'unico',
            'pagos'          => PagoItemResource::collection($pagos),
            'total_asignado' => (float) $pagos->sum(fn ($pago) => (float) ($pago['monto'] ?? 0)),

            // --- compatibilidad con el consumidor actual del Paso 3 ---
            'mpa_codigo'        => $esUnico ? ($primero['mpa_codigo'] ?? null) : null,
            'mpa_descri'        => $esUnico ? ($primero['mpa_descri'] ?? null) : null,
            'tiene_comprobante' => $esUnico ? !empty($primero['comprobante_temp']) : false,
        ];
    }
}