<?php
namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class NotaCredito extends Model
{
    protected $table      = 'nota_credito';
    protected $primaryKey = 'ncr_id';
    public    $timestamps = false;

    protected $fillable = [
        'ncr_comid', 'ncr_usuid', 'ncr_tpvid', 'ncr_timbra', 'ncr_timbvn',
        'ncr_numero', 'ncr_fecha', 'ncr_mncid',          // <-- ncr_motivo eliminado
        'ncr_exenta', 'ncr_grav5', 'ncr_grav10', 'ncr_iva5', 'ncr_iva10',
        'ncr_gravada', 'ncr_iva', 'ncr_total', 'ncr_obs',
    ];


    protected $casts = [
        'ncr_id'      => 'integer',
        'ncr_comid'   => 'integer',
        'ncr_mncid'   => 'integer',  
        'ncr_tpvid'   => 'integer',
        'ncr_timbvn'  => 'date',
        'ncr_fecha'   => 'date',
        'ncr_exenta'  => 'decimal:2',
        'ncr_grav5'   => 'decimal:2',
        'ncr_grav10'  => 'decimal:2',
        'ncr_iva5'    => 'decimal:2',
        'ncr_iva10'   => 'decimal:2',
        'ncr_gravada' => 'decimal:2',
        'ncr_iva'     => 'decimal:2',
        'ncr_total'   => 'decimal:2',
        'ncr_creado'  => 'datetime',
    ];

    public function compraCab()
    {
        return $this->belongsTo(CompraCab::class, 'ncr_comid', 'com_id');
    }

    public function detalle()
    {
        return $this->hasMany(DetNotaCredito::class, 'dnc_ncrid', 'ncr_id');
    }

    public function timbradoProveedor()
    {
        return $this->belongsTo(TimbradoProveedor::class, 'ncr_tpvid', 'tpv_id');
    }

    public function motivo(){
        return $this->belongsTo(MotivoNotaCredito::class, 'ncr_mncid', 'mnc_id');
    }
}