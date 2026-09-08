<?php
// app/Models/Compras/CompraDetalle.php
namespace App\Models\Compras;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;

class CompraDetalle extends Model
{
    protected $table      = 'compra_detalle';
    protected $primaryKey = 'cmd_id';
    public    $timestamps = false;

    protected $fillable = [
        'cmd_comid', 'cmd_prdid', 'cmd_tivid', 'cmd_nroprd', 'cmd_descri',
        'cmd_costo', 'cmd_despor', 'cmd_descto', 'cmd_tasa', 'cmd_gravada', 'cmd_iva',
        'cmd_canfac', 'cmd_canrec',
    ];

    protected $casts = [
        'cmd_id'      => 'integer',
        'cmd_comid'   => 'integer',
        'cmd_prdid'   => 'integer',
        'cmd_tivid'   => 'integer',
        'cmd_costo'   => 'decimal:4',
        'cmd_despor'  => 'decimal:2',
        'cmd_descto'  => 'decimal:2',
        'cmd_tasa'    => 'decimal:2',
        'cmd_gravada' => 'decimal:2',
        'cmd_iva'     => 'decimal:2',
        'cmd_canfac'  => 'decimal:4',
        'cmd_canrec'  => 'decimal:4',
    ];

    public function compraCab()
    {
        return $this->belongsTo(CompraCab::class, 'cmd_comid', 'com_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'cmd_prdid', 'prd_id');
    }

    public function tipoIva()
    {
        return $this->belongsTo(TipoIva::class, 'cmd_tivid', 'tiv_id');
    }
}