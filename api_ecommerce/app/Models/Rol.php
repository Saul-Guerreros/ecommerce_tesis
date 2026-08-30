<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'rol';
    protected $primaryKey = 'rol_id';
    public $timestamps = false;

    protected $fillable = [
        'rol_descri',
        'rol_estado',
    ];

    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'usu_rolid', 'rol_id');
    }

    public function detallePermisos()
    {
        return $this->hasMany(DetRolPermiso::class, 'drp_rolid', 'rol_id');
    }

    public function permisos()
    {
        return $this->belongsToMany(
            Permiso::class,
            'det_rol_permiso',
            'drp_rolid',
            'drp_prmid',
            'rol_id',
            'prm_id'
        );
    }

    // Comprueba si el rol tiene un permiso por su descripcion (slug)
    public function tienePermiso(string $permisoDescri): bool
    {
        return $this->permisos()->where('prm_descri', $permisoDescri)->exists();
    }
}
