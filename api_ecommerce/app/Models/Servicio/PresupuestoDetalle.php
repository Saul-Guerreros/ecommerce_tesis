<?php

namespace App\Models\Servicio;

use App\Models\Configuracion\Deposito;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;

class PresupuestoDetalle extends Model
{
    protected $table = 'presupuesto_detalle';
    protected $primaryKey = 'psd_id';
    public $timestamps = false;

    public const REPUESTO  = 'REPUESTO';
    public const MANO_OBRA = 'MANO_OBRA';

    protected $fillable = [
        'psd_pseid', 'psd_tipo', 'psd_prdid', 'psd_trbid', 'psd_depid', 'psd_descri',
        'psd_cantid', 'psd_precio', 'psd_subtot', 'psd_iva', 'psd_total', 'psd_reserv',
    ];

    protected $casts = [
        'psd_cantid' => 'decimal:4',
        'psd_precio' => 'decimal:2',
        'psd_subtot' => 'decimal:2',
        'psd_iva'    => 'decimal:2',
        'psd_total'  => 'decimal:2',
        'psd_reserv' => 'boolean',
    ];

    public function presupuesto()
    {
        return $this->belongsTo(PresupuestoServicio::class, 'psd_pseid', 'pse_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'psd_prdid', 'prd_id');
    }

    public function trabajo()
    {
        return $this->belongsTo(Trabajo::class, 'psd_trbid', 'trb_id');
    }

    public function deposito()
    {
        return $this->belongsTo(Deposito::class, 'psd_depid', 'dpo_id');
    }

    public function esRepuesto(): bool
    {
        return $this->psd_tipo === self::REPUESTO;
    }
}
