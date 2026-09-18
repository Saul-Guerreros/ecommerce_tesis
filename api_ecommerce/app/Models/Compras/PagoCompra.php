<?php
namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class PagoCompra extends Model
{
    protected $table      = 'pago_compra';
    protected $primaryKey = 'pgo_id';
    public    $timestamps = false;

    protected $fillable = [
        'pgo_cuoid', 'pgo_fecha', 'pgo_importe', 'pgo_formapa', 'pgo_observ', 'pgo_usuid',
    ];

    protected $casts = [
        'pgo_id'      => 'integer',
        'pgo_fecha'   => 'date',
        'pgo_importe' => 'decimal:2',
        'pgo_creado'  => 'datetime',
    ];

    public function cuota()
    {
        return $this->belongsTo(CuotaCompra::class, 'pgo_cuoid', 'cuo_id');
    }
}