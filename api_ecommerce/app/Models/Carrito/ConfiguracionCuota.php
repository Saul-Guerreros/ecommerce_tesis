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

    /** Precio financiado = Precio contado + (Precio contado × recargo% / 100) */
    public function precioFinanciado(float $precioContado): float
    {
        return round($precioContado * (1 + ((float) $this->cfc_recargo_pct / 100)));
    }

    /** Monto de cuota = Precio financiado / Número de cuotas */
    public function montoCuota(float $precioContado): float
    {
        return round($this->precioFinanciado($precioContado) / $this->cfc_cantidad);
    }
}