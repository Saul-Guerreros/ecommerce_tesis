<?php

namespace App\Models\Credito;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo PLANO de profesiones/oficios. No depende de tipo_profesion:
 * la relación laboral (Funcionario Público / Privado / Independiente)
 * se declara por separado en solicitud_credito.scr_tprid.
 */
class Profesion extends Model
{
    protected $table      = 'profesion';
    protected $primaryKey = 'pro_id';
    public    $timestamps = false;

    const CREATED_AT = 'pro_creado';

    protected $fillable = ['pro_descri', 'pro_estado'];

    protected $casts = [
        'pro_id'     => 'integer',
        'pro_estado' => 'integer',
        'pro_creado' => 'datetime',
    ];

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('pro_estado', 1);
    }
}