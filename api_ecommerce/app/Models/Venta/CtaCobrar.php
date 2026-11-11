<?php

namespace App\Models\Venta;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

use Illuminate\Database\Eloquent\Relations\HasOneThrough;

use Illuminate\Database\Eloquent\Relations\HasManyThrough;


/**
 * Una cuota a cobrar de una venta a credito. Espejo de CuotaCompra.
 *
 * Sin pagos parciales: cco_saldo vale cco_monto mientras esta PENDIENTE
 * y 0 cuando pasa a COBRADA. Lo garantiza ck_cco_coherencia.
 */
class CtaCobrar extends Model
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_COBRADA   = 'COBRADA';
    public const ESTADO_ANULADA   = 'ANULADA';

    protected $table      = 'cta_cobrar';
    protected $primaryKey = 'cco_id';
    public    $timestamps = false;

    const CREATED_AT = 'cco_creado';

    protected $fillable = [
        'cco_venid', 'cco_nrocuo', 'cco_femisi', 'cco_vencim',
        'cco_monto', 'cco_saldo', 'cco_compos', 'cco_estado', 'cco_feccob',
    ];

    protected $casts = [
        'cco_id'     => 'integer',
        'cco_venid'  => 'integer',
        'cco_nrocuo' => 'integer',
        'cco_femisi' => 'date',
        'cco_vencim' => 'date',
        'cco_monto'  => 'decimal:2',
        'cco_saldo'  => 'decimal:2',
        'cco_feccob' => 'date',
        'cco_creado' => 'datetime',
    ];

    // ---------- Scopes ----------

    public function scopePendiente(Builder $query): Builder
    {
        return $query->where('cco_estado', self::ESTADO_PENDIENTE);
    }

    /** Pendientes con fecha pasada: la cartera morosa. */
    public function scopeVencida(Builder $query): Builder
    {
        return $query->pendiente()->whereDate('cco_vencim', '<', now()->toDateString());
    }

    // ---------- Estado ----------

    public function estaPendiente(): bool
    {
        return $this->cco_estado === self::ESTADO_PENDIENTE;
    }

    public function estaVencida(): bool
    {
        return $this->estaPendiente() && $this->cco_vencim?->isPast() === true;
    }

    public function saldoPendiente(): float
    {
        return (float) $this->cco_saldo;
    }

    // ---------- Relaciones ----------

    public function factura(): BelongsTo
    {
        return $this->belongsTo(VentaCab::class, 'cco_venid', 'ven_id');
    }

        /** Líneas de recibo que cubren esta cuota (activas y anuladas). */
    public function detalles(): HasMany
    {
        return $this->hasMany(ReciboDetalle::class, 'rdt_ccoid', 'cco_id');
    }

    /** Recibo ACTIVO que saldó la cuota. Null si sigue pendiente. */
    public function recibo(): HasOneThrough
    {
        return $this->hasOneThrough(
            Recibo::class, ReciboDetalle::class,
            'rdt_ccoid',  // FK en recibo_detalle
            'rec_id',     // PK en recibo
            'cco_id',     // PK local
            'rdt_recid',  // FK en recibo_detalle hacia recibo
        )->where('rec_estado', Recibo::ESTADO_ACTIVO);
    }

        /** Líneas de declaración que incluyen esta cuota. */
    public function pagoDetalles(): HasMany
    {
        return $this->hasMany(CuotaPagoDetalle::class, 'cpd_ccoid', 'cco_id');
    }

    /** Declaraciones de pago del cliente que incluyen esta cuota. */
    public function pagos(): HasManyThrough
    {
        return $this->hasManyThrough(
            CuotaPago::class, CuotaPagoDetalle::class,
            'cpd_ccoid', 'cpa_id', 'cco_id', 'cpd_cpaid',
        )->orderByDesc('cpa_id');
    }

        /**
     * Declaración vigente: la última que no fue rechazada. Si el cliente
     * declaró, se la rechazaron y volvió a declarar, vale la nueva.
     */
    public function pagoVigente(): HasOneThrough
    {
        return $this->hasOneThrough(
            CuotaPago::class, CuotaPagoDetalle::class,
            'cpd_ccoid', 'cpa_id', 'cco_id', 'cpd_cpaid',
        )
        ->whereHas('estadoPago', fn ($q) => $q->where('epa_codigo', '!=', \App\Models\Pedido\EstadoPago::RECHAZADO))
        ->orderByDesc('cpa_id')
        ->limit(1);
    }

        /**
     * Ultima declaracion de la cuota, sea cual sea su estado.
     *
     * Distinta de pagoVigente(), que excluye las rechazadas porque el
     * admin solo puede confirmar una viva. Esta es la que lee la tienda:
     * sin ella, una declaracion rechazada desaparece de la pantalla y el
     * cliente nunca ve el motivo que tiene que corregir.
     */
    public function pagoUltimo(): HasOneThrough
    {
        return $this->hasOneThrough(
            CuotaPago::class, CuotaPagoDetalle::class,
            'cpd_ccoid', 'cpa_id', 'cco_id', 'cpd_cpaid',
        )
        ->orderByDesc('cpa_id')
        ->limit(1);
    }
}