<?php

namespace App\Models\Carrito;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opción de financiación de UN producto puntual (a diferencia de la vieja
 * PlanCuota, que era un plan único para toda la tienda).
 *
 * precio_financiado y monto_cuota se calculan siempre acá, nunca se
 * persisten: así nunca quedan desactualizados si cambia prd_pvtmin.
 */
class ConfiguracionCuota extends Model
{
    protected $table = 'configuracion_cuota';

    protected $primaryKey = 'cfc_id';

    public $timestamps = false;

    protected $fillable = [
        'cfc_prdid',
        'cfc_cantidad',
        'cfc_recargo_pct',
        'cfc_estado',
    ];

    protected $casts = [
        'cfc_id'          => 'integer',
        'cfc_prdid'       => 'integer',
        'cfc_cantidad'    => 'integer',
        'cfc_recargo_pct' => 'decimal:2',
        'cfc_estado'      => 'boolean',
        'cfc_creado'      => 'datetime',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'cfc_prdid', 'prd_id');
    }

    public function scopeActivas(Builder $q): Builder
    {
        return $q->where('cfc_estado', true);
    }

        /**
     * Precio con recargo SIN ajustar: Precio contado + (Precio contado × recargo% / 100).
     *
     * Es solo un paso intermedio para obtener la cuota. NO se muestra ni se
     * cobra: el precio financiado real es montoCuota() × cantidad de cuotas.
     */
    private function precioConRecargo(float $precioContado): float
    {
        return round($precioContado * (1 + ((float) $this->cfc_recargo_pct / 100)));
    }

    /** Monto de cuota = Precio con recargo / Número de cuotas, en guaraníes enteros. */
    public function montoCuota(float $precioContado): float
    {
        return round($this->precioConRecargo($precioContado) / $this->cfc_cantidad);
    }

    /**
     * Precio financiado = Monto de cuota × Número de cuotas.
     *
     * Se deriva de la cuota (y no al revés) para que el total sea SIEMPRE
     * múltiplo del número de cuotas. Así todas las cuotas del cronograma son
     * iguales: antes, un total impar (ej. 4.949.999 en 2 cuotas) dejaba la
     * última cuota 1 Gs. por debajo de las demás. La diferencia contra el
     * recargo exacto es de unos pocos guaraníes (máximo cantidad/2).
     */
    public function precioFinanciado(float $precioContado): float
    {
        return $this->montoCuota($precioContado) * $this->cfc_cantidad;
    }
}