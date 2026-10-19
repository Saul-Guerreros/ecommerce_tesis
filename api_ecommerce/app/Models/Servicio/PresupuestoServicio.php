<?php

namespace App\Models\Servicio;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresupuestoServicio extends Model
{
    protected $table = 'presupuesto_servicio';
    protected $primaryKey = 'pse_id';
    public $timestamps = false;

    public const DIAS_VIGENCIA = 7;

    protected $fillable = [
        'pse_numero', 'pse_oseid', 'pse_versio', 'pse_eprid',
        'pse_subtot', 'pse_impues', 'pse_total', 'pse_fecven',
        'pse_obs', 'pse_obsval', 'pse_motrec', 'pse_usuid', 'pse_valusu',
        'pse_enviad', 'pse_respon',
    ];

    protected $casts = [
        'pse_versio' => 'integer',
        'pse_subtot' => 'decimal:2',
        'pse_impues' => 'decimal:2',
        'pse_total'  => 'decimal:2',
        'pse_fecven' => 'date',
        'pse_enviad' => 'datetime',
        'pse_respon' => 'datetime',
        'pse_creado' => 'datetime',
    ];

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenServicio::class, 'pse_oseid', 'ose_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoPresupuesto::class, 'pse_eprid', 'epr_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PresupuestoDetalle::class, 'psd_pseid', 'pse_id')->orderBy('psd_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'pse_usuid', 'usu_id');
    }

    public function validador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'pse_valusu', 'usu_id');
    }

    public function codigoEstado(): ?string
    {
        return $this->estado?->epr_codigo;
    }

    public function esEditable(): bool
    {
        return $this->codigoEstado() === EstadoPresupuesto::BORRADOR;
    }
}
