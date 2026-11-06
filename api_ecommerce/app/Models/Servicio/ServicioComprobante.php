<?php

namespace App\Models\Servicio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comprobante de un pago del servicio (espejo de PagoComprobante, uno por pago).
 */
class ServicioComprobante extends Model
{
    protected $table = 'servicio_comprobante';
    protected $primaryKey = 'sco_id';
    public $timestamps = false;
    const CREATED_AT = 'sco_subido';

    protected $fillable = ['sco_spaid', 'sco_archiv', 'sco_verifi'];

    protected $casts = [
        'sco_verifi' => 'boolean',
        'sco_subido' => 'datetime',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(ServicioPago::class, 'sco_spaid', 'spa_id');
    }
}
