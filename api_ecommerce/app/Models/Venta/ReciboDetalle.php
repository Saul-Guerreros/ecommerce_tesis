<?php

namespace App\Models\Venta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una cuota cubierta por un recibo. Sin filas = recibo al contado.
 */
class ReciboDetalle extends Model
{
    protected $table      = 'recibo_detalle';
    protected $primaryKey = 'rdt_id';
    public    $timestamps = false;

    protected $fillable = ['rdt_recid', 'rdt_ccoid', 'rdt_monto'];

    protected $casts = [
        'rdt_id'    => 'integer',
        'rdt_recid' => 'integer',
        'rdt_ccoid' => 'integer',
        'rdt_monto' => 'decimal:2',
    ];

    public function recibo(): BelongsTo
    {
        return $this->belongsTo(Recibo::class, 'rdt_recid', 'rec_id');
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(CtaCobrar::class, 'rdt_ccoid', 'cco_id');
    }
}