<?php

namespace App\Models\Venta;

use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\PedidoPago;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Servicio\ServicioPago;

/**
 * Cobro registrado contra una factura de venta (espejo de PagoCompra).
 * No se edita ni se borra: se anula.
 */
class CobroVenta extends Model
{
    public const ESTADO_ANULADO = 0;
    public const ESTADO_ACTIVO  = 1;

    protected $table      = 'cobro_venta';
    protected $primaryKey = 'cob_id';
    public    $timestamps = false;

    protected $fillable = [
        'cob_venid', 'cob_mpaid', 'cob_monto', 'cob_nrotrx', 'cob_banco',
        'cob_fecha', 'cob_observ', 'cob_ppaid', 'cob_spaid', 'cob_usuid',
        'cob_estado', 'cob_fecanu', 'cob_anusid', 'cob_motanu',
    ];

    protected $casts = [
        'cob_id'     => 'integer',
        'cob_venid'  => 'integer',
        'cob_mpaid'  => 'integer',
        'cob_ppaid'  => 'integer',
        'cob_usuid'  => 'integer',
        'cob_monto'  => 'decimal:2',
        'cob_fecha'  => 'date',
        'cob_estado' => 'integer',
        'cob_creado' => 'datetime',
        'cob_fecanu' => 'datetime',
        'cob_spaid'  => 'integer',
    ];

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('cob_estado', self::ESTADO_ACTIVO);
    }

    public function estaActivo(): bool
    {
        return $this->cob_estado === self::ESTADO_ACTIVO;
    }

    // ---------- Relaciones ----------

    public function factura(): BelongsTo
    {
        return $this->belongsTo(VentaCab::class, 'cob_venid', 'ven_id');
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'cob_mpaid', 'mpa_id');
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(PedidoPago::class, 'cob_ppaid', 'ppa_id');
    }

    /** Pago del servicio técnico cobrado (excluyente con pago()). */
    public function pagoServicio(): BelongsTo
    {
        return $this->belongsTo(ServicioPago::class, 'cob_spaid', 'spa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cob_usuid', 'usu_id');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cob_anusid', 'usu_id');
    }
}