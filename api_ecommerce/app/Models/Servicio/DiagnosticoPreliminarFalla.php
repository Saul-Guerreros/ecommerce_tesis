<?php

namespace App\Models\Servicio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticoPreliminarFalla extends Model
{
    protected $table = 'diagnostico_preliminar_falla';
    protected $primaryKey = 'dpf_id';
    public $timestamps = false;

    protected $fillable = ['dpf_dprid', 'dpf_fllid', 'dpf_probab', 'dpf_orden'];

    public function falla(): BelongsTo
    {
        return $this->belongsTo(Falla::class, 'dpf_fllid', 'fll_id');
    }
}