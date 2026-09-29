<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    protected $table = 'persona';
    protected $primaryKey = 'per_id';
    public $timestamps = false;

    protected $fillable = [
        'per_tpdid',
        'per_nombr1',
        'per_nombr2',
        'per_apell1',
        'per_apell2',
        'per_cedula',
        'per_estciv',
        'per_fechna',
        'per_estado',
        'per_avatar',
    ];

    protected $casts = [
    'per_fechna' => 'date',
    ];

    // Una persona puede ser funcionario y cliente a la vez
    public function funcionario()
    {
        return $this->hasOne(Funcionario::class, 'fun_perid', 'per_id');
    }

    public function cliente()
    {
        return $this->hasOne(Cliente::class, 'cli_perid', 'per_id');
    }

    public function usuario()
    {
        return $this->hasOne(Usuario::class, 'usu_perid', 'per_id');
    }

    public function tipoDocumento()
    {
        return $this->belongsTo(TipoDocumento::class, 'per_tpdid', 'tpd_id');
    }

    public function direcciones()
    {
        return $this->hasMany(Direccion::class, 'dir_perid', 'per_id');
    }

    public function direccionPredeterminada()
    {
        return $this->hasOne(Direccion::class, 'dir_perid', 'per_id')->where('dir_predet', 1);
    }

    public function contactos()
    {
        return $this->hasMany(Contacto::class, 'con_perid', 'per_id');
    }

    // Accesor de conveniencia para nombre completo
    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->per_nombr1} {$this->per_nombr2} {$this->per_apell1} {$this->per_apell2}");
    }
}
