<?php

namespace App\Models\Servicio;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiagnosticoPreliminar extends Model
{
    public const GENERADO      = 'GENERADO';
    public const NO_DISPONIBLE = 'NO_DISPONIBLE';
    public const CANAL_WEB       = 'WEB';
    public const CANAL_MOSTRADOR = 'MOSTRADOR';
    public const NIVELES = ['ALTA', 'MEDIA', 'BAJA'];

    protected $table = 'diagnostico_preliminar';
    protected $primaryKey = 'dpr_id';
    public $timestamps = false;

    protected $fillable = [
        'dpr_sseid', 'dpr_usuid', 'dpr_canal', 'dpr_tdiid', 'dpr_marca', 'dpr_modelo', 'dpr_proble',
        'dpr_garant', 'dpr_estado', 'dpr_resume', 'dpr_confia', 'dpr_pregun', 'dpr_cosmin', 'dpr_cosmax',
        'dpr_dias', 'dpr_prmver', 'dpr_latms', 'dpr_coinci', 'dpr_evalua',
    ];

    protected $casts = [
        'dpr_garant' => 'boolean',
        'dpr_pregun' => 'array',
        'dpr_cosmin' => 'decimal:2',
        'dpr_cosmax' => 'decimal:2',
        'dpr_dias'   => 'integer',
        'dpr_latms'  => 'integer',
        'dpr_evalua' => 'datetime',
        'dpr_creado' => 'datetime',
    ];

    public function fallas(): HasMany
    {
        return $this->hasMany(DiagnosticoPreliminarFalla::class, 'dpf_dprid', 'dpr_id')->orderBy('dpf_orden');
    }

    public function trabajos(): HasMany
    {
        return $this->hasMany(DiagnosticoPreliminarTrabajo::class, 'dpt_dprid', 'dpr_id');
    }

    public function tipoDispositivo(): BelongsTo
    {
        return $this->belongsTo(TipoDispositivo::class, 'dpr_tdiid', 'tdi_id');
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudServicio::class, 'dpr_sseid', 'sse_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'dpr_usuid', 'usu_id');
    }

    public function generado(): bool
    {
        return $this->dpr_estado === self::GENERADO;
    }
}