<?php

namespace App\Models\Servicio;

use Illuminate\Database\Eloquent\Model;

class TipoDispositivo extends Model
{
    protected $table = 'tipo_dispositivo';
    protected $primaryKey = 'tdi_id';
    public $timestamps = false;

    protected $fillable = ['tdi_codigo', 'tdi_descri', 'tdi_estado'];

    protected $casts = [
        'tdi_estado' => 'integer',
        'tdi_creado' => 'datetime',
    ];

    public function fallas()
    {
        return $this->hasMany(Falla::class, 'fll_tdiid', 'tdi_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('tdi_estado', 1);
    }
}
