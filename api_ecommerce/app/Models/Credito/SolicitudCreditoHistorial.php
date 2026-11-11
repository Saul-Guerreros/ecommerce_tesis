<?php

namespace App\Models\Credito;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitacora de una transicion de estado.
 *
 * El registro es inmutable: no tiene update() en ningun service. Una
 * bitacora que se puede editar no prueba nada.
 */
class SolicitudCreditoHistorial extends Model
{
    protected $table      = 'solicitud_credito_historial';
    protected $primaryKey = 'sch_id';
    public    $timestamps = false;

    const CREATED_AT = 'sch_fecha';

    protected $fillable = [
        'sch_scrid', 'sch_escant', 'sch_escnue', 'sch_usuid', 'sch_observ',
    ];

    protected $casts = [
        'sch_scrid'  => 'integer',
        'sch_escant' => 'integer',
        'sch_escnue' => 'integer',
        'sch_usuid'  => 'integer',
        'sch_fecha'  => 'datetime',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCredito::class, 'sch_scrid', 'scr_id');
    }

    public function estadoAnterior(): BelongsTo
    {
        return $this->belongsTo(EstadoSolicitud::class, 'sch_escant', 'esc_id');
    }

    public function estadoNuevo(): BelongsTo
    {
        return $this->belongsTo(EstadoSolicitud::class, 'sch_escnue', 'esc_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'sch_usuid', 'usu_id');
    }
}