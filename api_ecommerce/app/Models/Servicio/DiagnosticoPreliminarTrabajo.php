<?php

namespace App\Models\Servicio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticoPreliminarTrabajo extends Model
{
    protected $table = 'diagnostico_preliminar_trabajo';
    protected $primaryKey = 'dpt_id';
    public $timestamps = false;

    protected $fillable = ['dpt_dprid', 'dpt_trbid', 'dpt_precio', 'dpt_dias'];

    protected $casts = ['dpt_precio' => 'decimal:2', 'dpt_dias' => 'integer'];

    public function trabajo(): BelongsTo
    {
        return $this->belongsTo(Trabajo::class, 'dpt_trbid', 'trb_id');
    }
}