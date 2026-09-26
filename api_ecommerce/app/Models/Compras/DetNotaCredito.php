<?php
namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class DetNotaCredito extends Model
{
    protected $table      = 'det_nota_credito';
    protected $primaryKey = 'dnc_id';
    public    $timestamps = false;

    protected $fillable = [
        'dnc_ncrid', 'dnc_cmdid', 'dnc_cantidad', 'dnc_despor',
        'dnc_descuento_unitario', 'dnc_base_gravada',
        'dnc_iva', 'dnc_total', 'dnc_canaju',
    ];

    protected $casts = [
        'dnc_id'                 => 'integer',
        'dnc_ncrid'               => 'integer',
        'dnc_cmdid'               => 'integer',
        'dnc_cantidad'            => 'decimal:4',
        'dnc_despor'              => 'decimal:2',
        'dnc_descuento_unitario'  => 'decimal:2',
        'dnc_base_gravada'        => 'decimal:2',
        'dnc_iva'                 => 'decimal:2',
        'dnc_total'               => 'decimal:2',
        'dnc_canaju'              => 'decimal:4',
    ];

    public function notaCredito()
    {
        return $this->belongsTo(NotaCredito::class, 'dnc_ncrid', 'ncr_id');
    }

    public function compraDetalle()
    {
        return $this->belongsTo(CompraDetalle::class, 'dnc_cmdid', 'cmd_id');
    }
}