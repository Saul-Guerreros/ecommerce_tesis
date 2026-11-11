<?php

namespace App\Models\Servicio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto de los datos de un cheque ANTES de [Editar] o [Descartar] (espejo de PagoChequeCambio). */
class ServicioChequeCambio extends Model
{
    protected $table = 'servicio_cheque_cambio';
    protected $primaryKey = 'scc_id';
    public $timestamps = false;
    const CREATED_AT = 'scc_fecha';

    public const DESCARTE = 'DESCARTE';
    public const EDICION  = 'EDICION';

    protected $fillable = [
        'scc_schid', 'scc_tipo', 'scc_tipche', 'scc_banco', 'scc_nrodoc',
        'scc_nombre', 'scc_nro', 'scc_fecobr', 'scc_motivo', 'scc_usuid',
    ];

    protected $casts = [
        'scc_fecobr' => 'date',
        'scc_fecha'  => 'datetime',
    ];

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(ServicioCheque::class, 'scc_schid', 'sch_id');
    }

    /** Se llama SIEMPRE antes del update: guarda lo que se pierde. */
    public static function registrar(ServicioCheque $cheque, string $tipo, ?int $usuarioId, ?string $motivo = null): self
    {
        return self::create([
            'scc_schid'  => $cheque->sch_id,
            'scc_tipo'   => $tipo,
            'scc_tipche' => $cheque->sch_tipo,
            'scc_banco'  => $cheque->sch_banco,
            'scc_nrodoc' => $cheque->sch_nrodoc,
            'scc_nombre' => $cheque->sch_nombre,
            'scc_nro'    => $cheque->sch_nro,
            'scc_fecobr' => $cheque->sch_fecobr,
            'scc_motivo' => $motivo,
            'scc_usuid'  => $usuarioId,
        ]);
    }
}