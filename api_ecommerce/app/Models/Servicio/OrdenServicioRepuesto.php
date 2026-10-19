<?php

namespace App\Models\Servicio;

use App\Models\Compras\MovimientoStock;
use App\Models\Configuracion\Deposito;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;

class OrdenServicioRepuesto extends Model
{
    protected $table = 'orden_servicio_repuesto';
    protected $primaryKey = 'osr_id';
    public $timestamps = false;

    protected $fillable = ['osr_oseid', 'osr_prdid', 'osr_depid', 'osr_cantap', 'osr_cantid', 'osr_precio', 'osr_presup'];

    protected $casts = [
        'osr_cantap' => 'decimal:4',
        'osr_cantid' => 'decimal:4',
        'osr_precio' => 'decimal:2',
        'osr_presup' => 'boolean',
    ];

    public function orden()
    {
        return $this->belongsTo(OrdenServicio::class, 'osr_oseid', 'ose_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'osr_prdid', 'prd_id');
    }

    public function deposito()
    {
        return $this->belongsTo(Deposito::class, 'osr_depid', 'dpo_id');
    }

    /** Salida de stock generada al cerrar la OT (trazabilidad del kardex). */
    public function movimiento()
    {
        return $this->hasOne(MovimientoStock::class, 'mst_osrid', 'osr_id');
    }
}
