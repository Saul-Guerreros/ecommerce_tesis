<?php

namespace App\Models\Carrito;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Plan de financiación disponible en la tienda (2x, 3x, 4x...).
 *
 * Es configuración comercial: se carga por seeder y se administra desde el
 * back-office. El recargo se guarda en porcentaje sobre el precio contado.
 */
class PlanCuota extends Model
{
    protected $table = 'plan_cuota';

    protected $primaryKey = 'pcu_id';

    // La tabla solo tiene pcu_creado (con DEFAULT CURRENT_TIMESTAMP) y no
    // tiene updated_at, así que Eloquent no maneja timestamps.
    public $timestamps = false;

    protected $fillable = [
        'pcu_cantidad',
        'pcu_recargo_pct',
        'pcu_orden',
        'pcu_estado',
    ];

    protected $casts = [
        'pcu_id'          => 'integer',
        'pcu_cantidad'    => 'integer',
        'pcu_recargo_pct' => 'decimal:2',
        'pcu_orden'       => 'integer',
        'pcu_estado'      => 'boolean',
        'pcu_creado'      => 'datetime',
    ];

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('pcu_estado', true);
    }
}