<?php

namespace App\Models\Credito;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila del cronograma consolidado ya persistido.
 */
class SolicitudCreditoCuota extends Model
{
    protected $table      = 'solicitud_credito_cuota';
    protected $primaryKey = 'scc_id';
    public    $timestamps = false;

    const CREATED_AT = 'scc_creado';

    protected $fillable = [
        'scc_scrid', 'scc_numero', 'scc_fecven', 'scc_import', 'scc_compos',
    ];

    protected $casts = [
        'scc_scrid'  => 'integer',
        'scc_numero' => 'integer',
        'scc_fecven' => 'date',
        'scc_import' => 'decimal:2',
        'scc_creado' => 'datetime',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCredito::class, 'scc_scrid', 'scr_id');
    }
}