<?php
// app/Models/Compras/AjusteInventario.php
namespace App\Models\Compras;

use App\Models\Configuracion\Deposito;
use Illuminate\Database\Eloquent\Model;

class AjusteInventario extends Model
{
    protected $table      = 'ajuste_inventario';
    protected $primaryKey = 'aji_id';
    public    $timestamps = false;

    const ESTADO_BORRADOR   = 0;
    const ESTADO_CONFIRMADO = 1;
    const ESTADO_ANULADO    = 2;

    protected $fillable = [
        'aji_usuid', 'aji_tpaid', 'aji_depid', 'aji_fecha',
        'aji_referencia', 'aji_obs', 'aji_estado',
    ];

    protected $casts = [
        'aji_id'     => 'integer',
        'aji_fecha'  => 'date',
        'aji_estado' => 'integer',
        'aji_creado' => 'datetime',
        'aji_confir' => 'datetime',
        'aji_anulado'=> 'datetime',
    ];

    public function tipoAjuste()
    {
        return $this->belongsTo(TipoAjuste::class, 'aji_tpaid', 'tpa_id');
    }

    public function deposito()
    {
        return $this->belongsTo(Deposito::class, 'aji_depid', 'dpo_id');
    }

    public function detalle()
    {
        return $this->hasMany(AjusteInventarioDetalle::class, 'aid_ajiid', 'aji_id');
    }
}