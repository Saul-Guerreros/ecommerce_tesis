<?php
// app/Models/Compras/AjusteInventarioDetalle.php
namespace App\Models\Compras;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;

class AjusteInventarioDetalle extends Model
{
    protected $table      = 'ajuste_inventario_detalle';
    protected $primaryKey = 'aid_id';
    public    $timestamps = false;

    protected $fillable = ['aid_prdid', 'aid_ajiid', 'aid_cantsis', 'aid_cantfis', 'aid_costo', 'aid_obs'];

    protected $casts = [
        'aid_id'      => 'integer',
        'aid_prdid'   => 'integer',
        'aid_ajiid'   => 'integer',
        'aid_cantsis' => 'decimal:4',
        'aid_cantfis' => 'decimal:4',
        'aid_costo'   => 'decimal:4',
    ];

    public function ajuste()
    {
        return $this->belongsTo(AjusteInventario::class, 'aid_ajiid', 'aji_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'aid_prdid', 'prd_id');
    }

    public function getDiferenciaAttribute(): string
    {
        return bcsub((string) $this->aid_cantfis, (string) ($this->aid_cantsis ?? 0), 4);
    }
}