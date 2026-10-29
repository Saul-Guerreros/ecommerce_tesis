<?php

namespace App\Models\Servicio;

use Illuminate\Database\Eloquent\Model;

class UrgenciaServicio extends Model
{
    protected $table = 'urgencia_servicio';
    protected $primaryKey = 'urg_id';
    public $timestamps = false;

    protected $fillable = ['urg_codigo', 'urg_descri', 'urg_criter', 'urg_color', 'urg_orden', 'urg_estado'];

    protected $casts = [
        'urg_orden'  => 'integer',
        'urg_estado' => 'integer',
        'urg_creado' => 'datetime',
    ];

    public function scopeActivas($query)
    {
        return $query->where('urg_estado', 1)->orderBy('urg_orden');
    }
}