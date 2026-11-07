<?php

namespace App\Models\Venta;

use App\Models\Configuracion\Sucursal;
use App\Models\Pedido\MetodoPago;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Devolución efectiva de dinero respaldada por una nota de crédito.
 * No se edita ni se borra: se anula.
 */
class DevolucionVenta extends Model
{
    public const ESTADO_ANULADA = 0;
    public const ESTADO_ACTIVA  = 1;

    protected $table      = 'devolucion_venta';
    protected $primaryKey = 'dev_id';
    public    $timestamps = false;

    protected $fillable = [
        'dev_ncvid', 'dev_mpaid', 'dev_monto', 'dev_nrotrx', 'dev_banco',
        'dev_fecha', 'dev_observ', 'dev_usuid', 'dev_sucid',
        'dev_estado', 'dev_fecanu', 'dev_anusid', 'dev_motanu',
    ];

    protected $casts = [
        'dev_id'     => 'integer',
        'dev_ncvid'  => 'integer',
        'dev_mpaid'  => 'integer',
        'dev_usuid'  => 'integer',
        'dev_sucid'  => 'integer',
        'dev_monto'  => 'decimal:2',
        'dev_fecha'  => 'date',
        'dev_estado' => 'integer',
        'dev_creado' => 'datetime',
        'dev_fecanu' => 'datetime',
    ];

    public function scopeActiva(Builder $query): Builder
    {
        return $query->where('dev_estado', self::ESTADO_ACTIVA);
    }

    public function notaCredito(): BelongsTo
    {
        return $this->belongsTo(NotaCreditoVenta::class, 'dev_ncvid', 'ncv_id');
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'dev_mpaid', 'mpa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'dev_usuid', 'usu_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'dev_sucid', 'suc_id');
    }

    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'dev_anusid', 'usu_id');
    }
}