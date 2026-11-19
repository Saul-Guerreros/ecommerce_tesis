<?php

namespace App\Models\Venta;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\MotivoRechazoPago;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Un metodo dentro de una declaracion de pago de cuotas.
 *
 * Es la unidad que el admin verifica: con pago dividido se rechaza el
 * comprobante de un medio sin tocar el otro, igual que en pedido_pago.
 */
class CuotaPagoMedio extends Model
{
    protected $table      = 'cuota_pago_medio';
    protected $primaryKey = 'cpm_id';
    public    $timestamps = false;

    const CREATED_AT = 'cpm_creado';

    protected $fillable = [
        'cpm_cpaid', 'cpm_orden', 'cpm_mpaid', 'cpm_monto', 'cpm_epaid',
        'cpm_nrotrx', 'cpm_banco', 'cpm_fechpag',
        'cpm_observ', 'cpm_mrpid', 'cpm_usuver', 'cpm_fechver',
    ];

    protected $casts = [
        'cpm_id'      => 'integer',
        'cpm_cpaid'   => 'integer',
        'cpm_orden'   => 'integer',
        'cpm_mpaid'   => 'integer',
        'cpm_epaid'   => 'integer',
        'cpm_monto'   => 'decimal:2',
        'cpm_fechpag' => 'date',
        'cpm_fechver' => 'datetime',
        'cpm_creado'  => 'datetime',
    ];

    // ---------- Relaciones ----------

    public function pago(): BelongsTo
    {
        return $this->belongsTo(CuotaPago::class, 'cpm_cpaid', 'cpa_id');
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'cpm_mpaid', 'mpa_id');
    }

    public function estadoPago(): BelongsTo
    {
        return $this->belongsTo(EstadoPago::class, 'cpm_epaid', 'epa_id');
    }

    public function motivoRechazo(): BelongsTo
    {
        return $this->belongsTo(MotivoRechazoPago::class, 'cpm_mrpid', 'mrp_id');
    }

    public function verificadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cpm_usuver', 'usu_id');
    }

    public function comprobante(): HasOne
    {
        return $this->hasOne(CuotaComprobante::class, 'ccm_cpmid', 'cpm_id');
    }

    public function cheque(): HasOne
    {
        return $this->hasOne(CuotaCheque::class, 'cch_cpmid', 'cpm_id');
    }

    // ---------- Estado ----------

    public function esCheque(): bool
    {
        return $this->metodoPago?->mpa_codigo === MetodoPago::CHEQUE;
    }

    public function fueRechazado(): bool
    {
        return $this->estadoPago?->epa_codigo === EstadoPago::RECHAZADO;
    }

    /**
     * El cliente puede subir un comprobante nuevo. Mismo criterio que en
     * el pedido: solo un medio rechazado y que lleve archivo.
     */
    public function esReemplazable(): bool
    {
        return $this->fueRechazado() && (bool) $this->metodoPago?->mpa_requcom;
    }
}