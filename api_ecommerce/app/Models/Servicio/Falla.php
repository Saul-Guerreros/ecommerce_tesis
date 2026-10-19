<?php

namespace App\Models\Servicio;

use Illuminate\Database\Eloquent\Model;

class Falla extends Model
{
    protected $table = 'falla';
    protected $primaryKey = 'fll_id';
    public $timestamps = false;

    protected $fillable = ['fll_codigo', 'fll_descri', 'fll_tdiid', 'fll_estado'];

    protected $casts = [
        'fll_tdiid'  => 'integer',
        'fll_estado' => 'integer',
        'fll_creado' => 'datetime',
    ];

    public function tipoDispositivo()
    {
        return $this->belongsTo(TipoDispositivo::class, 'fll_tdiid', 'tdi_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('fll_estado', 1);
    }

    /** Fallas aplicables a un tipo de equipo: las propias + las genéricas (NULL). */
    public function scopeParaTipo($query, ?int $tdiId)
    {
        return $query->where(fn ($q) => $q->whereNull('fll_tdiid')
            ->when($tdiId, fn ($q2) => $q2->orWhere('fll_tdiid', $tdiId)));
    }
}
