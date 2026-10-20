<?php

namespace App\Models\Credito;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de RELACIÓN LABORAL: Funcionario Público, Funcionario
 * Privado, Independiente. Es independiente de profesion -- las dos
 * listas se muestran completas en el formulario y ninguna filtra a la
 * otra (un Médico puede ser cualquiera de los tres).
 *
 * Conserva el nombre tipo_profesion porque así se llama el campo en el
 * formulario de solicitud de crédito ("Tipo de profesión *").
 */
class TipoProfesion extends Model
{
    protected $table      = 'tipo_profesion';
    protected $primaryKey = 'tpr_id';
    public    $timestamps = false;

    const CREATED_AT = 'tpr_creado';

    protected $fillable = ['tpr_descri', 'tpr_estado'];

    protected $casts = [
        'tpr_id'     => 'integer',
        'tpr_estado' => 'integer',
        'tpr_creado' => 'datetime',
    ];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('tpr_estado', 1);
    }
}