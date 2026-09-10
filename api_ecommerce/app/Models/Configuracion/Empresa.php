<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $table      = 'empresa';
    protected $primaryKey = 'emp_id';

    // La tabla no tiene created_at / updated_at: usa emp_creado.
    public $timestamps = false;

    protected $fillable = [
        'emp_razonsocial',
        'emp_ruc',
        'emp_telef',
        'emp_email',
        'emp_actividad',
        'emp_estado',
    ];

    protected $casts = [
        'emp_id'     => 'integer',
        'emp_estado' => 'integer',
        'emp_creado' => 'datetime',
    ];

    /**
     * Normaliza el RUC antes de guardarlo: sin espacios y en mayúsculas.
     * Evita que "80012345-6" y " 80012345-6 " se guarden como dos empresas
     * distintas y burlen el UNIQUE de la base de datos.
     */
    public function setEmpRucAttribute($value): void
    {
        $this->attributes['emp_ruc'] = strtoupper(trim($value));
    }

    public function setEmpEmailAttribute($value): void
    {
        $this->attributes['emp_email'] = $value ? strtolower(trim($value)) : null;
    }
}