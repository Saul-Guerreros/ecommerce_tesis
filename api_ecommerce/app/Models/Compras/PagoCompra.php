<?php
namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class PagoCompra extends Model
{
    protected $table      = 'pago_compra';
    protected $primaryKey = 'pgo_id';
    public    $timestamps = false;

    const FORMAS = ['Efectivo', 'Transferencia', 'Cheque', 'Otro'];

    const ESTADO_ANULADO = 0;
    const ESTADO_ACTIVO  = 1;

    protected $fillable = [
        'pgo_cuoid', 'pgo_fecha', 'pgo_importe', 'pgo_formapa',
        'pgo_referencia', 'pgo_observ', 'pgo_estado', 'pgo_usuid',
        'pgo_motanu',
    ];

    protected $casts = [
        'pgo_id'      => 'integer',
        'pgo_cuoid'   => 'integer',
        'pgo_fecha'   => 'date',
        'pgo_importe' => 'decimal:2',
        'pgo_estado'  => 'integer',
        'pgo_creado'  => 'datetime',
        'pgo_fecanu'  => 'datetime',
    ];

    public function cuota()
    {
        return $this->belongsTo(CuotaCompra::class, 'pgo_cuoid', 'cuo_id');
    }
}