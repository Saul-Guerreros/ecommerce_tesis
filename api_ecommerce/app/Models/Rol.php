<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    /** Permiso que habilita iniciar sesión y usar el panel admin (fila "Acceso al panel" en Permisos). */
    public const PERMISO_ACCESO_PANEL = 'dashboard.ver';

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

    /**
     * Roles que pueden usar el panel admin: el Administrador y cualquier rol
     * con el permiso "dashboard.ver". Cliente nunca (es el rol de la tienda).
     * No filtra por estado: el listado de usuarios debe seguir mostrando a
     * quienes tienen un rol que fue desactivado.
     */
    public function scopeConAccesoPanel($query)
    {
        return $query
            ->where('rol_descri', '!=', 'Cliente')
            ->where(function ($q) {
                $q->where('rol_descri', 'Administrador')
                  ->orWhereHas('permisos', fn ($p) => $p->where('prm_descri', self::PERMISO_ACCESO_PANEL));
            });
    }

    /** true si este rol está activo y puede iniciar sesión en el panel. */
    public function tieneAccesoPanel(): bool
    {
        return (int) $this->rol_estado === 1
            && static::conAccesoPanel()->whereKey($this->rol_id)->exists();
    }
}