<?php

namespace App\Models\Pedido;

use App\Models\Cliente;
use App\Models\Configuracion\Sucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

use App\Models\Pedido\PagoCheque;

class Pedido extends Model
{
    protected $table = 'pedido';
    protected $primaryKey = 'ped_id';

    const CREATED_AT = 'ped_creado';
    const UPDATED_AT = 'ped_actual';

        /**
     * Rechazos de comprobante que tolera un pedido antes de cancelarse.
     *
     * Tres intentos es el limite entre darle margen al cliente que se
     * equivoco de archivo y dejar stock reservado indefinidamente por
     * alguien que nunca va a pagar.
     */
    public const MAX_RECHAZOS_PAGO = 3;

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
        'ped_rechaz' => 'integer',
        'ped_vence'  => 'datetime',
        'ped_vencret' => 'datetime',
        'ped_creado' => 'datetime',
        'ped_actual' => 'datetime',
    ];

    // ---------- Vigencia de la reserva ----------

       /**
     * El pedido existe y reservó stock, pero el cliente todavía no declaró
     * su pago. Es el único estado en el que el Paso 3 del checkout puede
     * operar sobre el pedido.
     *
     * "Declaró su pago" ya no se deduce del estado: un pedido con cheque
     * queda en PENDIENTE_PAGO después de declararlo, porque el dinero
     * recién entra cuando el banco acredita. Lo que distingue a un pedido
     * abandonado de uno con cheque declarado es que éste ya tiene filas en
     * pedido_pago. Los demás métodos salen de PENDIENTE_PAGO al declarar,
     * así que para ellos la regla da exactamente el mismo resultado de
     * siempre.
     */
    public function pendienteDePago(): bool
    {
        return $this->estadoPago?->epa_codigo === EstadoPago::PENDIENTE_PAGO
            && !$this->pagos()->exists();
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
        return $query
            ->whereHas('estadoPago', fn ($q) => $q->where('epa_codigo', EstadoPago::PENDIENTE_PAGO))
            ->whereDoesntHave('pagos');
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

    /**
     * Pedidos con la reserva viva y el pago todavía de parte del cliente.
     *
     * PENDIENTE_PAGO sólo cuenta si el cliente no declaró nada. Con cheque
     * el pedido se queda en ese estado después de declarar, pero su plazo
     * no es el del pago: es el de entrega del papel (pch_fevcto), y lo
     * barre su propia pasada, no ésta.
     */
    public function scopeConPagoExpirable($query)
    {
        return $query->where(function ($q) {
            $q->whereHas('estadoPago', fn ($e) => $e->where('epa_codigo', EstadoPago::PENDIENTE_PAGO))
              ->whereDoesntHave('pagos');
        })->orWhereHas('estadoPago', fn ($e) => $e->where('epa_codigo', EstadoPago::RECHAZADO));
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

    /** Intentos que le quedan al cliente antes de que el pedido se cancele. */
    public function rechazosRestantes(): int
    {
        return max(0, self::MAX_RECHAZOS_PAGO - (int) $this->ped_rechaz);
    }

    /**
     * Hay un cheque del cliente en poder de la tienda.
     *
     * Cuenta como "hay algo del cliente acá" igual que un pago confirmado,
     * aunque todavía no haya entrado dinero: el papel está físicamente en
     * la sucursal y la tienda es responsable de él. Es lo que impide que
     * una tarea automática cancele un pedido cuyo cheque ya fue recibido y
     * está esperando que el banco acredite — en un diferido, semanas.
    */
    public function tieneChequeRecibido(): bool
    {
        return $this->pagos()
            ->whereHas('cheque', fn ($q) => $q->enPoderTienda())
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

    

        /**
     * Hay plata del cliente en el pedido: al menos un pago ya confirmado.
     *
     * Es la condición que impide que una tarea automática cancele el
     * pedido. Pasa con el pago dividido: se confirmó la transferencia y
     * se rechazó el segundo comprobante. Cancelarlo solo dejaría a la
     * tienda con el dinero Y la mercadería; eso necesita que una persona
     * contacte al cliente.
     */
    public function tienePagoConfirmado(): bool
    {
        return $this->pagos()
            ->whereHas('estadoPago', fn ($q) => $q->where('epa_codigo', EstadoPago::CONFIRMADO))
            ->exists();
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
    
    /** Factura VIGENTE de este pedido (null hasta que el admin la genere). */
    public function factura(): HasOne
    {
        return $this->hasOne(\App\Models\Venta\VentaCab::class, 'ven_pedid', 'ped_id')->vigente();
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