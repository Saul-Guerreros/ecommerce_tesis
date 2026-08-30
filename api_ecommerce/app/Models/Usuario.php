<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Usuario extends Authenticatable implements JWTSubject
{
    protected $table = 'usuario';
    protected $primaryKey = 'usu_id';
    public $timestamps = false;

    // Estados posibles de usu_estado
    public const ESTADO_ACTIVO = 'ACTIVO';
    public const ESTADO_INACTIVO = 'INACTIVO';
    public const ESTADO_BLOQUEADO = 'BLOQUEADO';

    protected $fillable = [
        'usu_login',
        'usu_passwd',
        'usu_estado',
        'usu_perid',
        'usu_rolid',
    ];

    protected $hidden = [
        'usu_passwd',
    ];

    /**
     * La tabla usuario no usa la columna "password" estandar de Laravel,
     * sino "usu_passwd". Sobreescribimos estos metodos para que el guard
     * 'api' (jwt) y el hashing de Laravel funcionen contra esa columna.
     */
    public function getAuthPassword()
    {
        return $this->usu_passwd;
    }

    public function getAuthPasswordName()
    {
        return 'usu_passwd';
    }

    // ----- Relaciones -----

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'usu_perid', 'per_id');
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'usu_rolid', 'rol_id');
    }

    public function sesiones()
    {
        return $this->hasMany(Sesion::class, 'ses_usuid', 'usu_id');
    }

    public function intentosSesion()
    {
        return $this->hasMany(IntentoSesion::class, 'ise_usuid', 'usu_id');
    }

    public function historialPasswords()
    {
        return $this->hasMany(HistorialPassword::class, 'hpa_usuid', 'usu_id');
    }

    public function tokensVerificacion()
    {
        return $this->hasMany(TokenVerificacion::class, 'tkv_usuid', 'usu_id');
    }

    // ----- Helpers de permisos -----

    public function tienePermiso(string $permisoDescri): bool
    {
        return $this->rol()->first()?->tienePermiso($permisoDescri) ?? false;
    }

    // ----- JWTSubject -----

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
