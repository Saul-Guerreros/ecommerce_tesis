<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $table = 'permiso';
    protected $primaryKey = 'prm_id';
    public $timestamps = false;

    protected $fillable = [
        'prm_descri',
        'prm_estado',
    ];

    public function roles()
    {
        return $this->belongsToMany(
            Rol::class,
            'det_rol_permiso',
            'drp_prmid',
            'drp_rolid',
            'prm_id',
            'rol_id'
        );
    }
}
