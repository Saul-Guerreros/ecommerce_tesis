<?php

namespace App\Models\Recomendacion;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo entrenado del recomendador: similitud coseno entre dos productos.
 * Clave primaria compuesta (psi_prdid, psi_simid): solo se lee y se
 * reemplaza completo por recomendaciones:entrenar, nunca se edita por id.
 */
class ProductoSimilitud extends Model
{
    protected $table = 'producto_similitud';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['psi_prdid', 'psi_simid', 'psi_score', 'psi_soporte', 'psi_calculado'];

    protected $casts = [
        'psi_prdid'     => 'integer',
        'psi_simid'     => 'integer',
        'psi_score'     => 'decimal:6',
        'psi_soporte'   => 'integer',
        'psi_calculado' => 'datetime',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'psi_prdid', 'prd_id');
    }

    public function similar(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'psi_simid', 'prd_id');
    }
}