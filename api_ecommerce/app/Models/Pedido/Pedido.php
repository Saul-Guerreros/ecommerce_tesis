<?php

namespace App\Models\Pedido;

use App\Models\Cliente;
use App\Models\Configuracion\Sucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pedido extends Model
{
    protected $table = 'pedido';
    protected $primaryKey = 'ped_id';

    const CREATED_AT = 'ped_creado';
    const UPDATED_AT = 'ped_actual';

    protected $fillable = [
        'ped_numero', 'ped_cliid', 'ped_subtot', 'ped_impues', 'ped_cosenv', 'ped_total',
        'ped_tenid', 'ped_sucid', 'ped_mpaid', 'ped_pagdiv', 'ped_estid', 'ped_epaid',
        'ped_vence', 'ped_vencret', 'ped_obsint',
    ];

    protected $casts = [
        'ped_subtot' => 'decimal:2',
        'ped_impues' => 'decimal:2',
        'ped_cosenv' => 'decimal:2',
        'ped_total'  => 'decimal:2',
        'ped_pagdiv' => 'boolean',
        'ped_vence'  => 'datetime',
        'ped_vencret' => 'datetime',
        'ped_creado' => 'datetime',
        'ped_actual' => 'datetime',
    ];

    // ---------- Vigencia de la reserva ----------

    /**
     * El pedido existe y reservo stock, pero el cliente todavia no declaro
     * su pago. Es el unico estado en el que el Paso 3 del checkout puede
     * operar sobre el pedido.
     */
    public function pendienteDePago(): bool
    {
        return $this->estadoPago?->epa_codigo === EstadoPago::PENDIENTE_PAGO;
    }

    /**
     * Se le acabo el plazo para pagar. Los pedidos del flujo anterior (sin
     * ped_vence) nunca vencen: su pago ya venia declarado desde el inicio.
     */
    public function vencido(): bool
    {
        return $this->ped_vence !== null && $this->ped_vence->isPast();
    }

    /**
     * El cliente todavia puede entrar a pagarlo.
     */
    public function pagable(): bool
    {
        return $this->pendienteDePago()
            && !$this->vencido()
            && $this->estadoPedido?->est_codigo !== EstadoPedido::CANCELADO;
    }

    public function scopePendienteDePago($query)
    {
        return $query->whereHas(
            'estadoPago',
            fn ($q) => $q->where('epa_codigo', EstadoPago::PENDIENTE_PAGO)
        );
    }

    /**
     * Estados de pago en los que la reserva sigue viva pero el pedido
     * todavia no se cobro, y por lo tanto puede vencer:
     *
     *   - PENDIENTE_PAGO: el cliente nunca declaro nada (checkout
     *     abandonado en el Paso 3).
     *   - RECHAZADO: el admin rechazo su comprobante y le reabrio el
     *     plazo para subir otro. Si no lo sube, la reserva no puede
     *     quedar tomada para siempre.
     */
    public const ESTADOS_PAGO_EXPIRABLES = [
        EstadoPago::PENDIENTE_PAGO,
        EstadoPago::RECHAZADO,
    ];

    public function scopeConPagoExpirable($query)
    {
        return $query->whereHas(
            'estadoPago',
            fn ($q) => $q->whereIn('epa_codigo', self::ESTADOS_PAGO_EXPIRABLES)
        );
    }

    /**
     * Hay plata del cliente en el pedido: al menos un pago ya confirmado.
     *
     * Es la condicion que impide que una tarea automatica cancele el
     * pedido. Pasa con el pago dividido: se confirmo la transferencia y
     * se rechazo el segundo comprobante. Cancelarlo solo dejaria a la
     * tienda con el dinero Y la mercaderia; eso necesita que una persona
     * contacte al cliente, igual que el caso del retiro vencido ya pagado.
     */
    // ---------- Plazo de retiro en sucursal ----------

    public function esRetiro(): bool
    {
        return $this->tipoEntrega?->ten_codigo === TipoEntrega::RETIRO;
    }

    /**
     * Se le pasó el plazo para venir a buscar el pedido.
     *
     * Distinto de vencido(), que mira el plazo de PAGO. Los dos relojes
     * nunca corren a la vez, así que nunca hay ambigüedad sobre cuál
     * mirar: si ped_vencret tiene valor, el pedido ya está LISTO y su
     * pago dejó de estar pendiente hace rato.
     */
    public function retiroVencido(): bool
    {
        return $this->ped_vencret !== null && $this->ped_vencret->isPast();
    }

    public function tienePagoConfirmado(): bool
    {
        return $this->pagos()
            ->whereHas('estadoPago', fn ($q) => $q->where('epa_codigo', EstadoPago::CONFIRMADO))
            ->exists();
    }

    /**
     * La reserva de este pedido puede liberarse de forma automatica.
     *
     * Reemplaza a pagable() como criterio del barrido: pagable() responde
     * "el cliente puede entrar a pagar en el Paso 3", que es una pregunta
     * distinta y mas estrecha (solo PENDIENTE_PAGO).
     */
    public function expirable(): bool
    {
        return in_array($this->estadoPago?->epa_codigo, self::ESTADOS_PAGO_EXPIRABLES, true)
            && $this->vencido()
            && $this->estadoPedido?->est_codigo !== EstadoPedido::CANCELADO
            && $this->estadoPedido?->est_codigo !== EstadoPedido::ENTREGADO
            && !$this->tienePagoConfirmado();
    }

    // ---------- Relaciones ----------

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'ped_cliid', 'cli_id');
    }

    public function tipoEntrega(): BelongsTo
    {
        return $this->belongsTo(TipoEntrega::class, 'ped_tenid', 'ten_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'ped_sucid', 'suc_id');
    }

    /**
     * Solo tiene valor cuando el pedido se pago con un unico metodo.
     * Con pago dividido es NULL: usar la relacion pagos().
     *
     * Desde el rediseno del checkout tambien es NULL mientras el pedido
     * esta en PENDIENTE_PAGO: el metodo se elige recien en el Paso 3.
     */
    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'ped_mpaid', 'mpa_id');
    }

    public function estadoPedido(): BelongsTo
    {
        return $this->belongsTo(EstadoPedido::class, 'ped_estid', 'est_id');
    }

    public function estadoPago(): BelongsTo
    {
        return $this->belongsTo(EstadoPago::class, 'ped_epaid', 'epa_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePedido::class, 'dpd_pedid', 'ped_id');
    }

    /**
     * Vacia mientras el pedido esta en PENDIENTE_PAGO; se puebla en el
     * Paso 3, con una fila por cada pago declarado.
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(PedidoPago::class, 'ppa_pedid', 'ped_id')->orderBy('ppa_orden');
    }

    public function entrega(): HasOne
    {
        return $this->hasOne(PedidoEntrega::class, 'pen_pedid', 'ped_id');
    }

    public function facturacion(): HasOne
    {
        return $this->hasOne(PedidoFacturacion::class, 'pfa_pedid', 'ped_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(PedidoHistorialEstado::class, 'peh_pedid', 'ped_id');
    }

    /**
     * Todos los comprobantes del pedido. Con pago dividido hay uno por cada
     * pago que lo requiera; cada fila apunta ademas a su pedido_pago via
     * pco_ppaid.
     */
    public function comprobantes(): HasMany
    {
        return $this->hasMany(PagoComprobante::class, 'pco_pedid', 'ped_id');
    }

    /**
     * @deprecated Se mantiene por compatibilidad con el codigo escrito antes
     * del pago dividido. Usar comprobantes() o pagos()->comprobante.
     */
    public function comprobante(): HasOne
    {
        return $this->hasOne(PagoComprobante::class, 'pco_pedid', 'ped_id');
    }
}