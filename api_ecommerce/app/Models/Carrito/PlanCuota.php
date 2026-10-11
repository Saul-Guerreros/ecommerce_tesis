<?php
namespace App\Models\Carrito;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PlanCuota extends Model
{
    protected $table = 'plan_cuota';
    protected $primaryKey = 'pcu_id';
    public $timestamps = false;

    const CREATED_AT = 'pcu_creado';
    const UPDATED_AT = null;

    protected $fillable = ['pcu_cantidad', 'pcu_recargo_pct', 'pcu_orden', 'pcu_estado'];

    protected $casts = [
        'pcu_cantidad'    => 'integer',
        'pcu_recargo_pct' => 'decimal:2',
        'pcu_estado'      => 'boolean',
        'pcu_creado'      => 'datetime',
    ];

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('pcu_estado', true);
    }
}