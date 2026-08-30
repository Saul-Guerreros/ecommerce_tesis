<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ciudad extends Model
{
    protected $table = 'ciudad';
    protected $primaryKey = 'ciu_id';
    public $timestamps = false;

    protected $fillable = [
        'ciu_depid',
        'ciu_descri',
        'ciu_estado',
    ];

    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'ciu_depid', 'dep_id');
    }

    public function barrios()
    {
        return $this->hasMany(Barrio::class, 'bar_ciuid', 'ciu_id');
    }

    public function calles()
    {
        return $this->hasMany(Calle::class, 'cal_ciuid', 'ciu_id');
    }
}
