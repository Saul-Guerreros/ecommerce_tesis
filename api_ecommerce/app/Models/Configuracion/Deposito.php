<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class Deposito extends Model
{
    protected $table      = 'deposito';
    protected $primaryKey = 'dpo_id';
    public    $timestamps = false;

    protected $fillable = [
        'dpo_sucid',
        'dpo_codigo',
        'dpo_nombre',
        'dpo_descri',
        'dpo_princi',
        'dpo_estado',
    ];

    protected $casts = [
        'dpo_id'     => 'integer',
        'dpo_sucid'  => 'integer',
        'dpo_princi' => 'integer',
        'dpo_estado' => 'integer',
        'dpo_creado' => 'datetime',
    ];

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'dpo_sucid', 'suc_id');
    }

    // El código se guarda normalizado para que el UNIQUE no se burle con
    // diferencias de mayúsculas o espacios ("dep01" vs "DEP01 ").
    public function setDpoCodigoAttribute($value): void
    {
        $this->attributes['dpo_codigo'] = strtoupper(trim($value));
    }
}