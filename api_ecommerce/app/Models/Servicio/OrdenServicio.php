<?php

namespace App\Models\Servicio;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrdenServicio extends Model
{
    protected $table = 'orden_servicio';
    protected $primaryKey = 'ose_id';
    public $timestamps = false;

    public const REPARADO     = 'REPARADO';
    public const PARCIAL      = 'PARCIAL';
    public const NO_REPARABLE = 'NO_REPARABLE';

    protected $fillable = [
        'ose_numero', 'ose_sseid', 'ose_usuid', 'ose_fecini', 'ose_fecfin',
        'ose_proced', 'ose_result', 'ose_gardia', 'ose_ajuste',
    ];

    protected $casts = [
        'ose_fecini' => 'datetime',
        'ose_fecfin' => 'datetime',
        'ose_gardia' => 'integer',
        'ose_ajuste' => 'boolean',
        'ose_creado' => 'datetime',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudServicio::class, 'ose_sseid', 'sse_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'ose_usuid', 'usu_id');
    }

    public function diagnostico(): HasOne
    {
        return $this->hasOne(Diagnostico::class, 'dia_oseid', 'ose_id');
    }

    /** Todas las versiones, de la más nueva a la más vieja. */
    public function presupuestos(): HasMany
    {
        return $this->hasMany(PresupuestoServicio::class, 'pse_oseid', 'ose_id')->orderByDesc('pse_versio');
    }

    /** La versión vigente (la de mayor pse_versio). */
    public function presupuestoActual(): HasOne
    {
        return $this->hasOne(PresupuestoServicio::class, 'pse_oseid', 'ose_id')->ofMany('pse_versio', 'max');
    }

    public function repuestos(): HasMany
    {
        return $this->hasMany(OrdenServicioRepuesto::class, 'osr_oseid', 'ose_id')->orderBy('osr_id');
    }

    public function estaCerrada(): bool
    {
        return !is_null($this->ose_result);
    }
}
