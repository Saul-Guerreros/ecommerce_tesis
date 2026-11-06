<?php
// app/Models/Inventario/MetricaModelo.php
namespace App\Models\Inventario;

use App\Models\Configuracion\Deposito;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;

class MetricaModelo extends Model
{
    protected $table      = 'metrica_modelo';
    protected $primaryKey = 'mmo_id';
    public    $timestamps = false;

    protected $fillable = [
        'mmo_prdid', 'mmo_depid', 'mmo_modelo', 'mmo_mae', 'mmo_wape',
        'mmo_semprb', 'mmo_elegido', 'mmo_creado','mmo_rmse',
    ];

    protected $casts = [
        'mmo_id'      => 'integer',
        'mmo_prdid'   => 'integer',
        'mmo_depid'   => 'integer',
        'mmo_mae'     => 'decimal:4',
        'mmo_wape'    => 'decimal:4',
        'mmo_semprb'  => 'integer',
        'mmo_elegido' => 'boolean',
        'mmo_creado'  => 'datetime',
        'mmo_rmse'    => 'decimal:4',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'mmo_prdid', 'prd_id');
    }

    public function deposito()
    {
        return $this->belongsTo(Deposito::class, 'mmo_depid', 'dpo_id');
    }
}
