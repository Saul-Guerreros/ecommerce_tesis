<?php
// app/Models/Compras/CuotaCompra.php
namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class CuotaCompra extends Model
{
    protected $table      = 'cuota_compra';
    protected $primaryKey = 'cuo_id';
    public    $timestamps = false;

    const ESTADO_PENDIENTE = 0;
    const ESTADO_PAGADA    = 1;

    protected $fillable = [
        'cuo_comid', 'cuo_nrocuo', 'cuo_venci', 'cuo_importe',
        'cuo_estado', 'cuo_fecpago', 'cuo_pagusu',
    ];

    protected $casts = [
        'cuo_id'      => 'integer',
        'cuo_nrocuo'  => 'integer',
        'cuo_venci'   => 'date',
        'cuo_importe' => 'decimal:2',
        'cuo_estado'  => 'integer',
        'cuo_fecpago' => 'date',
    ];

    public function compraCab()
    {
        return $this->belongsTo(CompraCab::class, 'cuo_comid', 'com_id');
    }

    /** Pago activo de la cuota (null si esta pendiente, o si su unico pago fue anulado). */
    public function pago()
    {
        return $this->hasOne(PagoCompra::class, 'pgo_cuoid', 'cuo_id')
                     ->where('pgo_estado', PagoCompra::ESTADO_ACTIVO);
    }

    /** Historial completo (incluye pagos anulados) — util para auditoria. */
    public function pagos()
    {
        return $this->hasMany(PagoCompra::class, 'pgo_cuoid', 'cuo_id');
    }

    public function saldoPendiente(): float
    {
        return $this->cuo_estado === self::ESTADO_PAGADA ? 0.0 : (float) $this->cuo_importe;
    }
}