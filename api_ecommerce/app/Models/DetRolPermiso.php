<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetRolPermiso extends Model
{
    protected $table = 'det_rol_permiso';
    protected $primaryKey = 'drp_id';
    public $timestamps = false;

    protected $fillable = [
        'drp_rolid',
        'drp_prmid',
    ];

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'drp_rolid', 'rol_id');
    }

    public function permiso()
    {
        return $this->belongsTo(Permiso::class, 'drp_prmid', 'prm_id');
    }
}
