<?php

namespace App\Models\Venta;

use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\PedidoPago;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Recibo: cobro registrado contra una factura de venta (espejo de
 * PagoCompra). No se edita ni se borra: se anula.
 *
 * Registra todos los cobros, pero solo se entrega al cliente cuando
 * corresponde al pago de cuotas de un credito: en una venta al contado
 * el documento del cliente es la factura, y rec_numero queda NULL.
 */
class Recibo extends Model
{
    public const ESTADO_ANULADO = 0;
    public const ESTADO_ACTIVO  = 1;

    protected $table      = 'recibo';
    protected $primaryKey = 'rec_id';
    public    $timestamps = false;

    protected $fillable = [
        'rec_numero', 'rec_venid', 'rec_mpaid', 'rec_monto', 'rec_nrotrx',
        'rec_banco', 'rec_fecha', 'rec_observ', 'rec_ppaid', 'rec_usuid',
        'rec_estado', 'rec_fecanu', 'rec_anusid', 'rec_motanu',
    ];

    protected $casts = [
        'rec_id'     => 'integer',
        'rec_venid'  => 'integer',
        'rec_mpaid'  => 'integer',
        'rec_ppaid'  => 'integer',
        'rec_usuid'  => 'integer',
        'rec_monto'  => 'decimal:2',
        'rec_fecha'  => 'date',
        'rec_estado' => 'integer',
        'rec_creado' => 'datetime',
        'rec_fecanu' => 'datetime',
    ];

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('rec_estado', self::ESTADO_ACTIVO);
    }

    public function estaActivo(): bool
    {
        return $this->rec_estado === self::ESTADO_ACTIVO;
    }

    // ---------- Relaciones ----------

    public function factura(): BelongsTo
    {
        return $this->belongsTo(VentaCab::class, 'rec_venid', 'ven_id');
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'rec_mpaid', 'mpa_id');
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(PedidoPago::class, 'rec_ppaid', 'ppa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'rec_usuid', 'usu_id');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'rec_anusid', 'usu_id');
    }

        /** Cuotas que cubre. Vacío en un recibo de venta al contado. */
    public function detalle(): HasMany
    {
        return $this->hasMany(ReciboDetalle::class, 'rdt_recid', 'rec_id');
    }

    /** Se entrega al cliente sólo si cubre cuotas. */
    public function esDeCuotas(): bool
    {
        return $this->rec_numero !== null;
    }
}