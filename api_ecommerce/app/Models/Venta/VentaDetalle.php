<?php

namespace App\Models\Venta;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaDetalle extends Model
{
    protected $table      = 'venta_detalle';
    protected $primaryKey = 'vnd_id';
    public    $timestamps = false;

    protected $fillable = [
        'vnd_venid', 'vnd_prdid', 'vnd_nroitem', 'vnd_codigo', 'vnd_descripcion',
        'vnd_umedida', 'vnd_nroserie', 'vnd_cantidad', 'vnd_precio', 'vnd_descuento',
        'vnd_tasa', 'vnd_valorventa', 'vnd_iva',
    ];

    protected $casts = [
        'vnd_id'         => 'integer',
        'vnd_venid'      => 'integer',
        'vnd_prdid'      => 'integer',
        'vnd_nroitem'    => 'integer',
        'vnd_cantidad'   => 'decimal:4',
        'vnd_precio'     => 'decimal:4',
        'vnd_descuento'  => 'decimal:2',
        'vnd_tasa'       => 'decimal:2',
        'vnd_valorventa' => 'decimal:2',
        'vnd_iva'        => 'decimal:2',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(VentaCab::class, 'vnd_venid', 'ven_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'vnd_prdid', 'prd_id');
    }
}