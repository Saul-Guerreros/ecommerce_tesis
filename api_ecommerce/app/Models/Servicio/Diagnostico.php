<?php

namespace App\Models\Servicio;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class Diagnostico extends Model
{
    protected $table = 'diagnostico';
    protected $primaryKey = 'dia_id';
    public $timestamps = false;

    protected $fillable = ['dia_oseid', 'dia_usuid', 'dia_causa', 'dia_repara', 'dia_reqrep', 'dia_tiempo', 'dia_fecha'];

    protected $casts = [
        'dia_repara' => 'boolean',
        'dia_reqrep' => 'boolean',
        'dia_tiempo' => 'integer',
        'dia_fecha'  => 'datetime',
    ];

    public function orden()
    {
        return $this->belongsTo(OrdenServicio::class, 'dia_oseid', 'ose_id');
    }

    public function tecnico()
    {
        return $this->belongsTo(Usuario::class, 'dia_usuid', 'usu_id');
    }

    public function fallas()
    {
        return $this->hasMany(DiagnosticoFalla::class, 'dfa_diaid', 'dia_id');
    }
}
