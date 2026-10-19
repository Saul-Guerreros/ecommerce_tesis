<?php

namespace App\Models\Servicio;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class RecepcionServicio extends Model
{
    protected $table = 'recepcion_servicio';
    protected $primaryKey = 'rse_id';
    public $timestamps = false;

    protected $fillable = ['rse_sseid', 'rse_usuid', 'rse_tecid', 'rse_fecha', 'rse_estfis', 'rse_clave', 'rse_obs'];

    protected $casts = [
        'rse_fecha' => 'datetime',
        // Cifrada con APP_KEY: en la BD nunca queda la clave en texto plano.
        'rse_clave' => 'encrypted',
    ];

    // La clave del equipo no viaja en ninguna serialización por accidente.
    protected $hidden = ['rse_clave'];

    public function solicitud()
    {
        return $this->belongsTo(SolicitudServicio::class, 'rse_sseid', 'sse_id');
    }

    public function recibidoPor()
    {
        return $this->belongsTo(Usuario::class, 'rse_usuid', 'usu_id');
    }

    public function tecnico()
    {
        return $this->belongsTo(Usuario::class, 'rse_tecid', 'usu_id');
    }

    public function accesorios()
    {
        return $this->hasMany(RecepcionAccesorio::class, 'rac_rseid', 'rse_id');
    }
}
