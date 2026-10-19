<?php
// app/Models/Compras/MovimientoStock.php
namespace App\Models\Compras;

use App\Models\Producto;
use App\Models\Configuracion\Deposito;
use App\Models\Pedido\DetallePedido;
use Illuminate\Database\Eloquent\Model;

class MovimientoStock extends Model
{
    protected $table      = 'movimiento_stock';
    protected $primaryKey = 'mst_id';
    public    $timestamps = false;

    protected $fillable = [
        'mst_aidid', 'mst_cmdid', 'mst_dncid', 'mst_dpdid', 'mst_usuid', 'mst_revid', 'mst_fecha', 'mst_tipo',
        'mst_cantid', 'mst_cosuni', 'mst_salcan', 'mst_salcos', 'mst_obs',
        'mst_stprid', 'mst_stdeid',
    ];

    protected $casts = [
        'mst_id'     => 'integer',
        'mst_cmdid'  => 'integer',
        'mst_dncid'  => 'integer',   // <- agregar
        'mst_dpdid'  => 'integer',
        'mst_revid'  => 'integer',
        'mst_fecha'  => 'datetime',
        'mst_cantid' => 'decimal:4',
        'mst_cosuni' => 'decimal:4',
        'mst_salcan' => 'decimal:4',
        'mst_salcos' => 'decimal:4',
        'mst_creado' => 'datetime',
        'mst_stprid' => 'integer',
        'mst_stdeid' => 'integer',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'mst_stprid', 'prd_id');
    }

    public function deposito()
    {
        return $this->belongsTo(Deposito::class, 'mst_stdeid', 'dpo_id');
    }

    public function compraDetalle()
    {
        return $this->belongsTo(CompraDetalle::class, 'mst_cmdid', 'cmd_id');
    }

    public function detNotaCredito()
    {
        return $this->belongsTo(DetNotaCredito::class, 'mst_dncid', 'dnc_id');
    }

    public function detallePedido()
    {
        return $this->belongsTo(DetallePedido::class, 'mst_dpdid', 'dpd_id');
    }
}