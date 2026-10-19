<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Funcionario extends Model
{
    protected $table = 'funcionario';
    protected $primaryKey = 'fun_id';
    public $timestamps = false;

    protected $fillable = [
        'fun_perid',
        'fun_crgid',
        'fun_rolid',
        'fun_fecing',
        'fun_fecegr',
        'fun_estado',
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'fun_perid', 'per_id');
    }

    public function cargo()
    {
        return $this->belongsTo(Cargo::class, 'fun_crgid', 'crg_id');
    }
	 // ... (agregar junto a la relación cargo())
    public function rol()
    {
        return $this->belongsTo(Rol::class, 'fun_rolid', 'rol_id');
    }
}
