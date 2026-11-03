<?php

namespace App\Models\Venta;

use Illuminate\Database\Eloquent\Builder;
use App\Models\Cliente;
use App\Models\Configuracion\Sucursal;
use App\Models\Configuracion\Timbrado;
use App\Models\Pedido\Pedido;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;


class VentaCab extends Model
{
    public const ESTADO_EMITIDA = 1;
    public const ESTADO_COBRADA = 2;
    public const ESTADO_ANULADA = 9;

    public const CONDICION_CONTADO = 1;
    public const CONDICION_CREDITO = 2;    

    protected $table      = 'venta_cab';
    protected $primaryKey = 'ven_id';
    public    $timestamps = false;

    protected $fillable = [
        'ven_timid', 'ven_cajid', 'ven_cliid', 'ven_usuid', 'ven_sucid', 'ven_pedid',
        'ven_timbrado', 'ven_timb_inicio', 'ven_timb_venc', 'ven_emp_ruc', 'ven_emp_razon', 'ven_establec', 'ven_puntoexp', 'ven_numero', 'ven_fchaem',
        'ven_razonsocial', 'ven_ruc', 'ven_ci', 'ven_direccion', 'ven_telefono', 'ven_email',
        'ven_condicion', 'ven_cant_cuotas', 'ven_nrorem',
        'ven_exenta', 'ven_grav5', 'ven_grav10', 'ven_subtotal', 'ven_total',
        'ven_iva5', 'ven_iva10', 'ven_totiva',
        'ven_estado', 'ven_obs', 'ven_anulado', 'ven_anusid', 'ven_motanu',
    ];

    protected $casts = [
        'ven_id'          => 'integer',
        'ven_timid'       => 'integer',
        'ven_cajid'       => 'integer',
        'ven_cliid'       => 'integer',
        'ven_usuid'       => 'integer',
        'ven_sucid'       => 'integer',
        'ven_pedid'       => 'integer',
        'ven_numero'      => 'integer',
        'ven_condicion'   => 'integer',
        'ven_estado'      => 'integer',
        'ven_timb_inicio' => 'date',
        'ven_timb_venc'   => 'date',
        'ven_fchaem'      => 'date',
        'ven_exenta'      => 'decimal:2',
        'ven_grav5'       => 'decimal:2',
        'ven_grav10'      => 'decimal:2',
        'ven_subtotal'    => 'decimal:2',
        'ven_total'       => 'decimal:2',
        'ven_iva5'        => 'decimal:2',
        'ven_iva10'       => 'decimal:2',
        'ven_totiva'      => 'decimal:2',
        'ven_creado'      => 'datetime',
        'ven_anulado'     => 'datetime',
    ];

    /**
     * Numero completo en formato paraguayo 3-3-7: 001-001-0000123.
     * Se arma, no se guarda: sus tres partes ya estan en columnas propias
     * y duplicarlas abriria la puerta a que queden desincronizadas.
     */
    public function getVenNrodocAttribute(): ?string
    {
        // Un borrador todavía no tiene número fiscal.
        if ($this->ven_numero === null) {
            return null;
        }

        return sprintf(
            '%s-%s-%s',
            $this->ven_establec,
            $this->ven_puntoexp,
            str_pad((string) $this->ven_numero, 7, '0', STR_PAD_LEFT)
        );
    }

    public function anulada(): bool
    {
        return $this->ven_estado === self::ESTADO_ANULADA;
    }

    /**
     * Una factura vigente es la que NO esta anulada (Emitida o Cobrada).
     * Es la condicion del indice uq_venta_pedido: un pedido puede tener
     * como maximo una factura vigente.
     */
    public function scopeVigente(Builder $query): Builder
    {
        return $query->where('ven_estado', '<>', self::ESTADO_ANULADA);
    }

    public function esVigente(): bool
    {
        return $this->ven_estado !== self::ESTADO_ANULADA;
    }

    // ---------- Relaciones ----------

    public function detalle(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'vnd_venid', 'ven_id')->orderBy('vnd_nroitem');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'ven_cliid', 'cli_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'ven_pedid', 'ped_id');
    }

    public function timbrado(): BelongsTo
    {
        return $this->belongsTo(Timbrado::class, 'ven_timid', 'tim_id');
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'ven_cajid', 'caj_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'ven_sucid', 'suc_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'ven_usuid', 'usu_id');
    }

    public function fe(): HasOne
    {
        return $this->hasOne(VentaFe::class, 'vfe_venid', 'ven_id');
    }

        public function cobros(): HasMany
    {
        return $this->hasMany(CobroVenta::class, 'cob_venid', 'ven_id')->orderBy('cob_id');
    }

    /** Suma de los cobros activos. Cuando alcanza ven_total la factura pasa a Cobrada. */
    public function totalCobrado(): float{
        return (float) $this->cobros()->activo()->sum('cob_monto');
    }

    public function notasCredito(): HasMany{
        return $this->hasMany(NotaCreditoVenta::class, 'ncv_venid', 'ven_id')->orderByDesc('ncv_id');
    }

    /**
     * Solo las NC no anuladas. Relación aparte y SIN orderBy a propósito: así
     * sirve para withCount() en el listado (un ORDER BY dentro de un COUNT
     * rompe en PostgreSQL) y para el chequeo de anulación.
     */
    public function notasCreditoVigentes(): HasMany{
        return $this->hasMany(NotaCreditoVenta::class, 'ncv_venid', 'ven_id')
            ->where('ncv_estado', '<>', NotaCreditoVenta::ESTADO_ANULADA);
    }

    /**
     * Total acreditado por notas de crédito VIGENTES. Se calcula, no se
     * acumula en una columna: mismo criterio que totalCobrado(), porque un
     * saldo guardado se desincroniza en cuanto se anula una NC.
     */
    public function totalAcreditado(): float
    {
        return (float) $this->notasCredito()->vigente()->sum('ncv_total');
    }

    /** Lo que la factura todavía representa como deuda del cliente. */
    public function saldo(): float
    {
        return round((float) $this->ven_total - $this->totalAcreditado() - $this->totalCobrado(), 2);
    }

}