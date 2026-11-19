<?php

namespace App\Models\Venta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una cuota cubierta por una declaración de pago del cliente. */
class CuotaPagoDetalle extends Model
{
    protected $table      = 'cuota_pago_detalle';
    protected $primaryKey = 'cpd_id';
    public    $timestamps = false;

    protected $fillable = ['cpd_cpaid', 'cpd_ccoid', 'cpd_monto'];

    protected $casts = [
        'cpd_id'    => 'integer',
        'cpd_cpaid' => 'integer',
        'cpd_ccoid' => 'integer',
        'cpd_monto' => 'decimal:2',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(CuotaPago::class, 'cpd_cpaid', 'cpa_id');
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(CtaCobrar::class, 'cpd_ccoid', 'cco_id');
    }
}