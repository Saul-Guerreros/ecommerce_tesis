<?php

namespace App\Models\Venta;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetNotaCreditoVenta extends Model
{
    protected $table      = 'det_nota_credito_venta';
    protected $primaryKey = 'dnv_id';
    public    $timestamps = false;

    protected $fillable = [
        'dnv_ncvid', 'dnv_vndid', 'dnv_prdid', 'dnv_nroitem',
        'dnv_codigo', 'dnv_descripcion', 'dnv_umedida',
        'dnv_cantidad', 'dnv_precio', 'dnv_tasa', 'dnv_valorventa', 'dnv_iva', 'dnv_canrei',
    ];

    protected $casts = [
        'dnv_id'         => 'integer',
        'dnv_ncvid'      => 'integer',
        'dnv_vndid'      => 'integer',
        'dnv_prdid'      => 'integer',
        'dnv_nroitem'    => 'integer',
        'dnv_cantidad'   => 'decimal:4',
        'dnv_precio'     => 'decimal:4',
        'dnv_tasa'       => 'decimal:2',
        'dnv_valorventa' => 'decimal:2',
        'dnv_iva'        => 'decimal:2',
        'dnv_canrei'     => 'decimal:4',
    ];

    public function notaCredito(): BelongsTo
    {
        return $this->belongsTo(NotaCreditoVenta::class, 'dnv_ncvid', 'ncv_id');
    }

    public function lineaFactura(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class, 'dnv_vndid', 'vnd_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'dnv_prdid', 'prd_id');
    }
}