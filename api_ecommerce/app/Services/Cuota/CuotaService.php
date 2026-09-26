<?php

namespace App\Services\Cuota;

use App\Models\Carrito\PlanCuota;
use Illuminate\Support\Collection;

/**
 * Calcula las opciones de pago en cuotas que se muestran en la vitrina
 * (cuotero del home y del detalle de producto).
 *
 * Los planes viven en la tabla `plan_cuota` y no cambian durante un request,
 * por eso se resuelven una sola vez y se reutilizan: el home formatea hasta
 * ~70 productos y sin memoización sería una consulta por producto (N+1).
 */
class CuotaService
{
    /** Cache por request de los planes activos. */
    private ?Collection $planes = null;

    /**
     * @return array<int, array{cantidad:int, recargo_pct:float, monto_cuota:float, total:float}>
     */
    public function calcularOpciones(float $precioContado): array
    {
        if ($precioContado <= 0) {
            return [];
        }

        return $this->planes()
            ->map(function (PlanCuota $plan) use ($precioContado) {
                // El recargo se aplica sobre el total y recién después se
                // divide: así la suma de las cuotas siempre da el total
                // mostrado (evita diferencias de 1 Gs por redondeo).
                $total = round($precioContado * (1 + ((float) $plan->pcu_recargo_pct / 100)));

                return [
                    'cantidad'    => (int) $plan->pcu_cantidad,
                    'recargo_pct' => (float) $plan->pcu_recargo_pct,
                    'monto_cuota' => round($total / $plan->pcu_cantidad),
                    'total'       => $total,
                ];
            })
            ->values()
            ->all();
    }

    /** @return Collection<int, PlanCuota> */
    private function planes(): Collection
    {
        return $this->planes ??= PlanCuota::activos()
            ->orderBy('pcu_orden')
            ->get();
    }
}