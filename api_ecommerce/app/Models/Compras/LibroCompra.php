<?php
namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class LibroCompra extends Model
{
    protected $table      = 'libro_compra';
    protected $primaryKey = 'lcm_id';
    public    $timestamps = false;

    const TIPO_FACTURA      = 'FC';
    const TIPO_NOTA_CREDITO = 'NC';

    const ESTADO_VIGENTE = 1;
    const ESTADO_ANULADO = 2;

    protected $fillable = [
        'lcm_tipo', 'lcm_comid', 'lcm_ncrid', 'lcm_estado', 'lcm_fecha', 'lcm_nrodoc',
        'lcm_proraz', 'lcm_proruc', 'lcm_exenta', 'lcm_grav5', 'lcm_grav10',
        'lcm_iva5', 'lcm_iva10', 'lcm_total',
    ];

    protected $casts = [
        'lcm_fecha'  => 'date',
        'lcm_estado' => 'integer',
        'lcm_exenta' => 'decimal:2',
        'lcm_grav5'  => 'decimal:2',
        'lcm_grav10' => 'decimal:2',
        'lcm_iva5'   => 'decimal:2',
        'lcm_iva10'  => 'decimal:2',
        'lcm_total'  => 'decimal:2',
        'lcm_creado' => 'datetime',
    ];

    public function compraCab()   { return $this->belongsTo(CompraCab::class, 'lcm_comid', 'com_id'); }
    public function notaCredito() { return $this->belongsTo(NotaCredito::class, 'lcm_ncrid', 'ncr_id'); }
}