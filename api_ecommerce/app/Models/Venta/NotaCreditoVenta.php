<?php

namespace App\Models\Venta;

use App\Models\Cliente;
use App\Models\Compras\MotivoNotaCredito;
use App\Models\Configuracion\Deposito;
use App\Models\Configuracion\Sucursal;
use App\Models\Configuracion\Timbrado;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Relations\HasOne;

class NotaCreditoVenta extends Model
{
    public const ESTADO_EMITIDA = 1;
    public const ESTADO_ANULADA = 9;

    protected $table      = 'nota_credito_venta';
    protected $primaryKey = 'ncv_id';
    public    $timestamps = false;

    protected $fillable = [
        'ncv_venid', 'ncv_timid', 'ncv_cajid', 'ncv_cliid', 'ncv_usuid', 'ncv_sucid',
        'ncv_mncid', 'ncv_depid',
        'ncv_timbrado', 'ncv_timb_inicio', 'ncv_timb_venc', 'ncv_emp_ruc', 'ncv_emp_razon',
        'ncv_establec', 'ncv_puntoexp', 'ncv_numero', 'ncv_fecha',
        'ncv_razonsocial', 'ncv_ruc', 'ncv_ci',
        'ncv_exenta', 'ncv_grav5', 'ncv_grav10', 'ncv_subtotal', 'ncv_total',
        'ncv_iva5', 'ncv_iva10', 'ncv_totiva',
        'ncv_estado', 'ncv_obs', 'ncv_anulado', 'ncv_anusid', 'ncv_motanu',
    ];

    protected $casts = [
        'ncv_id'          => 'integer',
        'ncv_venid'       => 'integer',
        'ncv_timid'       => 'integer',
        'ncv_cajid'       => 'integer',
        'ncv_cliid'       => 'integer',
        'ncv_usuid'       => 'integer',
        'ncv_sucid'       => 'integer',
        'ncv_mncid'       => 'integer',
        'ncv_depid'       => 'integer',
        'ncv_numero'      => 'integer',
        'ncv_estado'      => 'integer',
        'ncv_timb_inicio' => 'date',
        'ncv_timb_venc'   => 'date',
        'ncv_fecha'       => 'date',
        'ncv_exenta'      => 'decimal:2',
        'ncv_grav5'       => 'decimal:2',
        'ncv_grav10'      => 'decimal:2',
        'ncv_subtotal'    => 'decimal:2',
        'ncv_total'       => 'decimal:2',
        'ncv_iva5'        => 'decimal:2',
        'ncv_iva10'       => 'decimal:2',
        'ncv_totiva'      => 'decimal:2',
        'ncv_creado'      => 'datetime',
        'ncv_anulado'     => 'datetime',
    ];

    /**
     * Número completo 3-3-7 (001-001-0000045). Se arma, no se guarda:
     * sus tres partes ya están en columnas propias y duplicarlas abriría
     * la puerta a que queden desincronizadas. Mismo criterio que
     * VentaCab::getVenNrodocAttribute().
     */
    public function getNcvNrodocAttribute(): string
    {
        return sprintf(
            '%s-%s-%s',
            $this->ncv_establec,
            $this->ncv_puntoexp,
            str_pad((string) $this->ncv_numero, 7, '0', STR_PAD_LEFT)
        );
    }

    /** Vigente = no anulada. Solo las vigentes consumen saldo acreditable. */
    public function scopeVigente(Builder $query): Builder
    {
        return $query->where('ncv_estado', '<>', self::ESTADO_ANULADA);
    }

    public function anulada(): bool
    {
        return $this->ncv_estado === self::ESTADO_ANULADA;
    }

    public function afectaStock(): bool
    {
        return $this->motivo?->afectaStock() ?? false;
    }

    // ---------- Relaciones ----------

    public function factura(): BelongsTo
    {
        return $this->belongsTo(VentaCab::class, 'ncv_venid', 'ven_id');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(DetNotaCreditoVenta::class, 'dnv_ncvid', 'ncv_id')->orderBy('dnv_nroitem');
    }

    public function motivo(): BelongsTo
    {
        return $this->belongsTo(MotivoNotaCredito::class, 'ncv_mncid', 'mnc_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'ncv_cliid', 'cli_id');
    }

    public function timbrado(): BelongsTo
    {
        return $this->belongsTo(Timbrado::class, 'ncv_timid', 'tim_id');
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'ncv_cajid', 'caj_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'ncv_sucid', 'suc_id');
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Deposito::class, 'ncv_depid', 'dpo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'ncv_usuid', 'usu_id');
    }

    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'ncv_anusid', 'usu_id');
    }

    /**
    * Declaracion electronica. Nunca es null en una NC emitida con la
    * facturacion activa: la fila se crea en la misma transaccion.
    */
    public function fe(): HasOne
    {
        return $this->hasOne(NotaCreditoVentaFe::class, 'nfe_ncvid', 'ncv_id');
    }
}