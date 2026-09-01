<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    protected $table      = 'departamento';
    protected $primaryKey = 'dep_id';
    public    $timestamps = false;

    protected $fillable = ['dep_descri', 'dep_estado'];

    protected $casts = [
        'dep_id'     => 'integer',
        'dep_estado' => 'integer',
        'dep_creado' => 'datetime',
    ];
}