<?php

namespace App\Models\Servicio;

use Illuminate\Database\Eloquent\Model;

class DiagnosticoFalla extends Model
{
    protected $table = 'diagnostico_falla';
    protected $primaryKey = 'dfa_id';
    public $timestamps = false;

    protected $fillable = ['dfa_diaid', 'dfa_fllid', 'dfa_obs'];

    public function diagnostico()
    {
        return $this->belongsTo(Diagnostico::class, 'dfa_diaid', 'dia_id');
    }

    public function falla()
    {
        return $this->belongsTo(Falla::class, 'dfa_fllid', 'fll_id');
    }
}
