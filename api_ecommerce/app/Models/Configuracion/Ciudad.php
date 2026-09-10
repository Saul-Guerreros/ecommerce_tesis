<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class Ciudad extends Model
{
    protected $table      = 'ciudad';
    protected $primaryKey = 'ciu_id';
    public    $timestamps = false;

    protected $fillable = ['ciu_depid', 'ciu_descri', 'ciu_estado'];

    protected $casts = [
        'ciu_id'     => 'integer',
        'ciu_depid'  => 'integer',
        'ciu_estado' => 'integer',
        'ciu_creado' => 'datetime',
    ];

    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'ciu_depid', 'dep_id');
    }
}