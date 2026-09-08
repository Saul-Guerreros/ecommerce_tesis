<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class Timbrado extends Model
{
    protected $table      = 'timbrado';
    protected $primaryKey = 'tim_id';
    public    $timestamps = false;

    protected $fillable = [
        'tim_tcoid', 'tim_sucid', 'tim_numero', 'tim_puntex',
        'tim_fechin', 'tim_fechfn', 'tim_nroini', 'tim_nrofin', 'tim_nroact',
        'tim_observ', 'tim_estado',
    ];

    protected $casts = [
        'tim_id'     => 'integer',
        'tim_tcoid'  => 'integer',
        'tim_sucid'  => 'integer',
        'tim_nroini' => 'integer',
        'tim_nrofin' => 'integer',
        'tim_nroact' => 'integer',
        'tim_estado' => 'integer',
        'tim_fechin' => 'date',
        'tim_fechfn' => 'date',
        'tim_creado' => 'datetime',
    ];

    public function tipoComprobante()
    {
        return $this->belongsTo(TipoComprobante::class, 'tim_tcoid', 'tco_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'tim_sucid', 'suc_id');
    }

    /**
     * Un timbrado es utilizable si esta activo Y hoy cae dentro de su rango
     * de vigencia. tim_estado por si solo no alcanza: una fecha vencida
     * invalida el timbrado aunque nadie lo haya desactivado a mano.
     */
    public function getVigenteAttribute(): bool
    {
        $hoy = now()->toDateString();

        return $this->tim_estado == 1
            && $this->tim_fechin->toDateString() <= $hoy
            && $this->tim_fechfn->toDateString() >= $hoy;
    }
}