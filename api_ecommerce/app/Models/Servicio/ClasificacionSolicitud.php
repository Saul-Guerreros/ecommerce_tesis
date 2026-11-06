<?php

namespace App\Models\Servicio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Punto 7 - Clasificación automática (IA) de una solicitud de servicio. */
class ClasificacionSolicitud extends Model
{
    public const PENDIENTE     = 'PENDIENTE';
    public const IA            = 'IA';
    public const NO_DISPONIBLE = 'NO_DISPONIBLE';
    public const MANUAL        = 'MANUAL';

    protected $table = 'clasificacion_solicitud';
    protected $primaryKey = 'cls_id';

    const CREATED_AT = 'cls_creado';
    const UPDATED_AT = 'cls_actual';

    protected $fillable = [
        'cls_sseid', 'cls_estado', 'cls_tdicli',
        'cls_tdiia', 'cls_fllia', 'cls_cafia', 'cls_urgia', 'cls_confia', 'cls_motivo',
        'cls_tdiid', 'cls_cafid', 'cls_urgid',
        'cls_cafdia', 'cls_evalua', 'cls_revisa',
        'cls_dprid', 'cls_prmver', 'cls_latms', 'cls_intent',
    ];

    protected $casts = [
        'cls_latms'  => 'integer',
        'cls_intent' => 'integer',
        'cls_evalua' => 'datetime',
        'cls_revisa' => 'datetime',
        'cls_creado' => 'datetime',
        'cls_actual' => 'datetime',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudServicio::class, 'cls_sseid', 'sse_id');
    }

    public function tipoDispositivo(): BelongsTo
    {
        return $this->belongsTo(TipoDispositivo::class, 'cls_tdiid', 'tdi_id');
    }

    public function tipoDispositivoIa(): BelongsTo
    {
        return $this->belongsTo(TipoDispositivo::class, 'cls_tdiia', 'tdi_id');
    }

    public function tipoDispositivoCliente(): BelongsTo
    {
        return $this->belongsTo(TipoDispositivo::class, 'cls_tdicli', 'tdi_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaFalla::class, 'cls_cafid', 'caf_id');
    }

    public function urgencia(): BelongsTo
    {
        return $this->belongsTo(UrgenciaServicio::class, 'cls_urgid', 'urg_id');
    }

    public function fallaIa(): BelongsTo
    {
        return $this->belongsTo(Falla::class, 'cls_fllia', 'fll_id');
    }

    public function correcciones(): HasMany
    {
        return $this->hasMany(ClasificacionCorreccion::class, 'ccr_clsid', 'cls_id')->orderByDesc('ccr_id');
    }

    /** El cliente declaró un tipo distinto al que determinó la IA. */
    public function dispositivoInconsistente(): bool
    {
        return $this->cls_tdicli !== null && $this->cls_tdiia !== null
            && (int) $this->cls_tdicli !== (int) $this->cls_tdiia;
    }
}