<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class Calle extends Model
{
    protected $table      = 'calle';
    protected $primaryKey = 'cal_id';
    public    $timestamps = false;

    protected $fillable = ['cal_barid', 'cal_descri', 'cal_estado'];

    protected $casts = [
        'cal_id'     => 'integer',
        'cal_barid'  => 'integer',
        'cal_estado' => 'integer',
        'cal_creado' => 'datetime',
    ];

    public function barrio()
    {
        return $this->belongsTo(Barrio::class, 'cal_barid', 'bar_id');
    }

    /**
     * No existe relacion directa calle -> ciudad (no hay columna cal_ciuid).
     * Este helper resuelve la ciudad navegando calle -> barrio -> ciudad,
     * util para filtros/consultas puntuales sin tener que repetir el join
     * a mano en cada lugar que lo necesite.
     */
    public function scopeDeCiudad($query, int $ciudadId)
    {
        return $query->whereHas('barrio', fn ($q) => $q->where('bar_ciuid', $ciudadId));
    }
}
