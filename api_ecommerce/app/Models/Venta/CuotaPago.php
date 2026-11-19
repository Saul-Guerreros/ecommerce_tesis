<?php

namespace App\Models\Venta;

use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\MotivoRechazoPago;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cobro declarado por el cliente sobre una o varias cuotas.
 *
 * Cabecera: cuanto declaro en total y en que estado general esta. El
 * detalle dice que cuotas cubre (cuota_pago_detalle) y los medios con
 * que las paga (cuota_pago_medio).
 *
 * Es una DECLARACION, no un hecho: el hecho es el Recibo, que registra
 * el admin al validar.
 */
class CuotaPago extends Model
{
    protected $table      = 'cuota_pago';
    protected $primaryKey = 'cpa_id';
    public    $timestamps = false;

    const CREATED_AT = 'cpa_creado';

    protected $fillable = [
        'cpa_mpaid', 'cpa_monto', 'cpa_epaid',
        'cpa_nrotrx', 'cpa_banco', 'cpa_fechpag', 'cpa_observ',
        'cpa_mrpid', 'cpa_usuver', 'cpa_fechver',
    ];

    protected $casts = [
        'cpa_id'      => 'integer',
        'cpa_mpaid'   => 'integer',
        'cpa_epaid'   => 'integer',
        'cpa_monto'   => 'decimal:2',
        'cpa_fechpag' => 'date',
        'cpa_fechver' => 'datetime',
        'cpa_creado'  => 'datetime',
    ];

    /** Cuotas que cubre esta declaración. */
    public function detalle(): HasMany
    {
        return $this->hasMany(CuotaPagoDetalle::class, 'cpd_cpaid', 'cpa_id');
    }

    /** Métodos con los que el cliente la cubre. Uno, o varios si dividió. */
    public function medios(): HasMany
    {
        return $this->hasMany(CuotaPagoMedio::class, 'cpm_cpaid', 'cpa_id')
                    ->orderBy('cpm_orden');
    }

    /**
     * Método de la declaración. Sólo tiene valor cuando el cliente pagó
     * con uno solo: con pago dividido la pregunta no tiene respuesta y
     * hay que mirar los medios.
     */
    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'cpa_mpaid', 'mpa_id');
    }

    public function estadoPago(): BelongsTo
    {
        return $this->belongsTo(EstadoPago::class, 'cpa_epaid', 'epa_id');
    }

    public function motivoRechazo(): BelongsTo
    {
        return $this->belongsTo(MotivoRechazoPago::class, 'cpa_mrpid', 'mrp_id');
    }

    public function verificadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cpa_usuver', 'usu_id');
    }

    // ---------- Lectura ----------

    public function esDividida(): bool
    {
        return $this->medios->count() > 1;
    }

    /** Lo que todavía espera verificación del admin. */
    public function montoEnRevision(): float
    {
        return (float) $this->medios
            ->filter(fn (CuotaPagoMedio $m) => (bool) $m->metodoPago?->mpa_requcom)
            ->sum('cpm_monto');
    }
}