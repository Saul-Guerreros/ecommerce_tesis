<?php

namespace App\Models\Pedido;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PedidoPago extends Model
{
    protected $table = 'pedido_pago';
    protected $primaryKey = 'ppa_id';
    public $timestamps = false;
    const CREATED_AT = 'ppa_creado';

    protected $fillable = [
        'ppa_pedid', 'ppa_orden', 'ppa_mpaid', 'ppa_monto', 'ppa_epaid', 'ppa_observ',
        'ppa_nrotrx', 'ppa_banco', 'ppa_fechpag', 'ppa_montver',
        'ppa_mrpid', 'ppa_usuver', 'ppa_fechver',
    ];

    protected $casts = [
        'ppa_orden'   => 'integer',
        'ppa_monto'   => 'decimal:2',
        'ppa_montver' => 'decimal:2',
        'ppa_fechpag' => 'date',
        'ppa_fechver' => 'datetime',
        'ppa_creado'  => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'ppa_pedid', 'ped_id');
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'ppa_mpaid', 'mpa_id');
    }

    public function estadoPago(): BelongsTo
    {
        return $this->belongsTo(EstadoPago::class, 'ppa_epaid', 'epa_id');
    }

    public function comprobante(): HasOne
    {
        return $this->hasOne(PagoComprobante::class, 'pco_ppaid', 'ppa_id');
    }

    public function motivoRechazo(): BelongsTo
    {
        return $this->belongsTo(MotivoRechazoPago::class, 'ppa_mrpid', 'mrp_id');
    }

    public function usuarioVerificador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'ppa_usuver', 'usu_id');
    }
}