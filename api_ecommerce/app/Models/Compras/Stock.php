<?php
// app/Models/Compras/Stock.php
namespace App\Models\Compras;

use App\Models\Producto;
use App\Models\Configuracion\Deposito;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $table      = 'stock';
    protected $primaryKey = 'sto_id';
    public    $timestamps = false;

    protected $fillable = ['sto_prdid', 'sto_depid', 'sto_cantid', 'sto_reserv', 'sto_cosprm', 'sto_actual'];

    protected $casts = [
        'sto_id'     => 'integer',
        'sto_prdid'  => 'integer',
        'sto_depid'  => 'integer',
        'sto_cantid' => 'decimal:4',
        'sto_reserv' => 'decimal:4',
        'sto_cosprm' => 'decimal:4',
        'sto_actual' => 'datetime',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'sto_prdid', 'prd_id');
    }

    public function deposito()
    {
        return $this->belongsTo(Deposito::class, 'sto_depid', 'dpo_id');
    }
}