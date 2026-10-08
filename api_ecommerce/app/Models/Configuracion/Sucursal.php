<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    protected $table      = 'sucursal';
    protected $primaryKey = 'suc_id';
    public    $timestamps = false;

    protected $fillable = [
        'suc_empid', 'suc_ciuid', 'suc_nombre', 'suc_princi', 'suc_establ',
        'suc_direccion', 'suc_telef', 'suc_cel', 'suc_email', 'suc_estado',
        'suc_retiro', 'suc_horario',
    ];

    protected $casts = [
        'suc_id' => 'integer', 'suc_empid' => 'integer', 'suc_ciuid' => 'integer',
        'suc_princi' => 'integer', 'suc_estado' => 'integer', 'suc_creado' => 'datetime',
        'suc_retiro' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'suc_empid', 'emp_id');
    }

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'suc_ciuid', 'ciu_id');
    }

    public function setSucEmailAttribute($value): void
    {
        $this->attributes['suc_email'] = $value ? strtolower(trim($value)) : null;
    }

        public function scopeParaRetiro($query)
    {
        return $query->where('suc_estado', 1)->where('suc_retiro', true);
    }
}