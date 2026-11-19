<?php

namespace App\Models\Venta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comprobante de UN medio de pago, no de la declaración entera: un cobro
 * dividido en dos transferencias lleva dos archivos distintos.
 */
class CuotaComprobante extends Model
{
    protected $table      = 'cuota_comprobante';
    protected $primaryKey = 'ccm_id';
    public    $timestamps = false;

    const CREATED_AT = 'ccm_subido';

    protected $fillable = ['ccm_cpmid', 'ccm_archiv', 'ccm_verifi'];

    protected $casts = [
        'ccm_id'     => 'integer',
        'ccm_cpmid'  => 'integer',
        'ccm_verifi' => 'boolean',
        'ccm_subido' => 'datetime',
    ];

    public function medio(): BelongsTo
    {
        return $this->belongsTo(CuotaPagoMedio::class, 'ccm_cpmid', 'cpm_id');
    }
}