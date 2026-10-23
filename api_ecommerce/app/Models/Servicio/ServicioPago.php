<?php

namespace App\Models\Servicio;

use App\Models\Configuracion\Sucursal;
use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\MetodoPago;
use App\Models\Pedido\MotivoRechazoPago;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pago del servicio técnico (espejo de PedidoPago + datos del cobro presencial).
 */
class ServicioPago extends Model
{
    protected $table = 'servicio_pago';
    protected $primaryKey = 'spa_id';
    public $timestamps = false;
    const CREATED_AT = 'spa_creado';

    public const TIPO_ANTICIPO = 'ANTICIPO';
    public const TIPO_SALDO    = 'SALDO';
    public const TIPO_TOTAL    = 'TOTAL';

    public const CANAL_WEB       = 'WEB';
    public const CANAL_MOSTRADOR = 'MOSTRADOR';

    protected $fillable = [
        'spa_sseid', 'spa_orden', 'spa_tipo', 'spa_canal', 'spa_mpaid', 'spa_monto', 'spa_epaid', 'spa_observ',
        'spa_nrotrx', 'spa_banco', 'spa_fechpag', 'spa_montver',
        'spa_mrpid', 'spa_usuver', 'spa_fechver',
        'spa_usucob', 'spa_sucid',
    ];

    protected $casts = [
        'spa_orden'   => 'integer',
        'spa_monto'   => 'decimal:2',
        'spa_montver' => 'decimal:2',
        'spa_fechpag' => 'date',
        'spa_fechver' => 'datetime',
        'spa_creado'  => 'datetime',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudServicio::class, 'spa_sseid', 'sse_id');
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'spa_mpaid', 'mpa_id');
    }

    public function estadoPago(): BelongsTo
    {
        return $this->belongsTo(EstadoPago::class, 'spa_epaid', 'epa_id');
    }

    public function comprobante(): HasOne
    {
        return $this->hasOne(ServicioComprobante::class, 'sco_spaid', 'spa_id');
    }

    public function motivoRechazo(): BelongsTo
    {
        return $this->belongsTo(MotivoRechazoPago::class, 'spa_mrpid', 'mrp_id');
    }

    public function usuarioVerificador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'spa_usuver', 'usu_id');
    }

    public function usuarioCobro(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'spa_usucob', 'usu_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'spa_sucid', 'suc_id');
    }
}
