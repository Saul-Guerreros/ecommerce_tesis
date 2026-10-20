<?php

namespace App\Models\Credito;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudCreditoDetalle extends Model
{
    protected $table      = 'solicitud_credito_detalle';
    protected $primaryKey = 'scd_id';
    public    $timestamps = false;

    protected $fillable = [
        'scd_scrid', 'scd_prdid', 'scd_descri', 'scd_cantid', 'scd_preuni',
        'scd_numcuo', 'scd_recpct', 'scd_moncuo', 'scd_subtot', 'scd_iva',
    ];

    protected $casts = [
        'scd_scrid'  => 'integer',
        'scd_prdid'  => 'integer',
        'scd_numcuo' => 'integer',
        'scd_cantid' => 'decimal:2',
        'scd_preuni' => 'decimal:2',
        'scd_recpct' => 'decimal:2',
        'scd_moncuo' => 'decimal:2',
        'scd_subtot' => 'decimal:2',
        'scd_iva'    => 'decimal:2',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCredito::class, 'scd_scrid', 'scr_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'scd_prdid', 'prd_id');
    }

    /**
     * Cuota mensual de la linea completa. scd_moncuo es la cuota POR
     * UNIDAD (igual que carrito.car_montocuo), asi que la linea paga esa
     * cuota multiplicada por la cantidad.
     */
    public function cuotaMensualLinea(): float
    {
        return (float) $this->scd_moncuo * (float) $this->scd_cantid;
    }

    /** Lo que costaria esta linea al contado, sin financiacion. */
    public function totalContado(): float
    {
        return (float) $this->scd_preuni * (float) $this->scd_cantid;
    }

    /** Lo que el cliente paga de mas por financiar esta linea. */
    public function recargo(): float
    {
        return (float) $this->scd_subtot - $this->totalContado();
    }
}