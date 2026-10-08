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
        'ped_tenid', 'ped_sucid', 'ped_mpaid', 'ped_pagdiv', 'ped_estid', 'ped_epaid', 'ped_obsint',
    ];

    protected $casts = [
        'ped_subtot' => 'decimal:2',
        'ped_impues' => 'decimal:2',
        'ped_cosenv' => 'decimal:2',
        'ped_total'  => 'decimal:2',
        'ped_pagdiv' => 'boolean',
        'ped_creado' => 'datetime',
        'ped_actual' => 'datetime',
    ];

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
     * Siempre tiene al menos una fila, aunque el cliente haya elegido un
     * solo metodo de pago.
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