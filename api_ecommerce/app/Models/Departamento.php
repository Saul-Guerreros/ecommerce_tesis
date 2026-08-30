<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    protected $table = 'departamento';
    protected $primaryKey = 'dep_id';
    public $timestamps = false;

    protected $fillable = [
        'dep_descri',
        'dep_estado',
    ];

    public function ciudades()
    {
        return $this->hasMany(Ciudad::class, 'ciu_depid', 'dep_id');
    }
}
