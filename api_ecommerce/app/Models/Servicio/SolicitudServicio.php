<?php

namespace App\Models\Servicio;

use App\Models\Cliente;
use App\Models\Configuracion\Sucursal;
use App\Models\Direccion;
use App\Models\Pedido\EstadoPago;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\TipoEntrega;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SolicitudServicio extends Model
{
    protected $table = 'solicitud_servicio';
    protected $primaryKey = 'sse_id';

    // sse_creado / sse_actual cumplen el rol de created_at / updated_at
    const CREATED_AT = 'sse_creado';
    const UPDATED_AT = 'sse_actual';

    protected $fillable = [
        'sse_numero', 'sse_cliid',
        'sse_tdiid', 'sse_marca', 'sse_modelo', 'sse_nroser', 'sse_proble', 'sse_acceso',
        'sse_garant', 'sse_pedid', 'sse_sseori',
        'sse_tenid', 'sse_sucid', 'sse_dirid', 'sse_fecing', 'sse_cosret',
        'sse_etsid', 'sse_elging', 'sse_epaid', 'sse_elgegr',
        'sse_subtot', 'sse_impues', 'sse_cosenv', 'sse_total',
    ];

    protected $casts = [
        'sse_garant' => 'boolean',
        'sse_fecing' => 'date',
        'sse_cosret' => 'decimal:2',
        'sse_subtot' => 'decimal:2',
        'sse_impues' => 'decimal:2',
        'sse_cosenv' => 'decimal:2',
        'sse_total'  => 'decimal:2',
        'sse_creado' => 'datetime',
        'sse_actual' => 'datetime',
    ];

    // ---------- Relaciones ----------

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'sse_cliid', 'cli_id');
    }

    public function tipoDispositivo(): BelongsTo
    {
        return $this->belongsTo(TipoDispositivo::class, 'sse_tdiid', 'tdi_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'sse_pedid', 'ped_id');
    }

    public function solicitudOriginal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sse_sseori', 'sse_id');
    }

    public function tipoEntrega(): BelongsTo
    {
        return $this->belongsTo(TipoEntrega::class, 'sse_tenid', 'ten_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sse_sucid', 'suc_id');
    }

    public function direccion(): BelongsTo
    {
        return $this->belongsTo(Direccion::class, 'sse_dirid', 'dir_id');
    }

    public function estadoServicio(): BelongsTo
    {
        return $this->belongsTo(EstadoServicio::class, 'sse_etsid', 'ets_id');
    }

    public function estadoIngreso(): BelongsTo
    {
        return $this->belongsTo(EstadoLogistica::class, 'sse_elging', 'elg_id');
    }

    public function estadoPago(): BelongsTo
    {
        return $this->belongsTo(EstadoPago::class, 'sse_epaid', 'epa_id');
    }

    public function estadoEgreso(): BelongsTo
    {
        return $this->belongsTo(EstadoLogistica::class, 'sse_elgegr', 'elg_id');
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(SolicitudAdjunto::class, 'sad_sseid', 'sse_id');
    }

    public function recepcion(): HasOne
    {
        return $this->hasOne(RecepcionServicio::class, 'rse_sseid', 'sse_id');
    }

    public function ordenServicio(): HasOne
    {
        return $this->hasOne(OrdenServicio::class, 'ose_sseid', 'sse_id');
    }

    public function entrega(): HasOne
    {
        return $this->hasOne(EntregaServicio::class, 'ese_sseid', 'sse_id');
    }

    /** Reclamos de garantía abiertos sobre esta solicitud. */
    public function reclamos(): HasMany
    {
        return $this->hasMany(self::class, 'sse_sseori', 'sse_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(ServicioHistorialEstado::class, 'she_sseid', 'sse_id')->orderBy('she_fecha')->orderBy('she_id');
    }

    // ---------- Ayudas de dominio ----------

    /** true = el taller retira el equipo del domicilio (tipo_entrega ENVIO). */
    public function esADomicilio(): bool
    {
        return $this->tipoEntrega?->ten_codigo === TipoEntrega::ENVIO;
    }

    public function codigoServicio(): ?string
    {
        return $this->estadoServicio?->ets_codigo;
    }

    /**
     * Garantía del TRABAJO: corre desde la entrega por ose_gardia días.
     * Devuelve la fecha de vencimiento o null si no aplica.
     */
    public function garantiaVenceEl(): ?\Illuminate\Support\Carbon
    {
        $orden = $this->ordenServicio;

        if (!$this->entrega || !$orden || $orden->ose_gardia <= 0
            || !in_array($orden->ose_result, [OrdenServicio::REPARADO, OrdenServicio::PARCIAL], true)) {
            return null;
        }

        return $this->entrega->ese_fecha->copy()->addDays($orden->ose_gardia)->endOfDay();
    }

    public function codigoIngreso(): ?string
    {
        return $this->estadoIngreso?->elg_codigo;
    }
}
