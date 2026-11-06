<?php
// app/Models/Inventario/PrediccionDemanda.php
namespace App\Models\Inventario;

use App\Models\Configuracion\Deposito;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;

class PrediccionDemanda extends Model
{
    protected $table      = 'prediccion_demanda';
    protected $primaryKey = 'pdd_id';
    public    $timestamps = false;

    protected $fillable = ['pdd_prdid', 'pdd_depid', 'pdd_periodo', 'pdd_cantid', 'pdd_modelo', 'pdd_creado'];

    protected $casts = [
        'pdd_id'      => 'integer',
        'pdd_prdid'   => 'integer',
        'pdd_depid'   => 'integer',
        'pdd_periodo' => 'date',
        'pdd_cantid'  => 'decimal:4',
        'pdd_creado'  => 'datetime',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'pdd_prdid', 'prd_id');
    }

    public function deposito()
    {
        return $this->belongsTo(Deposito::class, 'pdd_depid', 'dpo_id');
    }
}
