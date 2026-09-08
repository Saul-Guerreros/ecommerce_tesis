<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class Barrio extends Model
{
    protected $table      = 'barrio';
    protected $primaryKey = 'bar_id';
    public    $timestamps = false;

    protected $fillable = ['bar_ciuid', 'bar_descri', 'bar_estado'];

    protected $casts = [
        'bar_id'     => 'integer',
        'bar_ciuid'  => 'integer',
        'bar_estado' => 'integer',
        'bar_creado' => 'datetime',
    ];

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'bar_ciuid', 'ciu_id');
    }

    public function calles()
    {
        return $this->hasMany(Calle::class, 'cal_barid', 'bar_id');
    }
}
