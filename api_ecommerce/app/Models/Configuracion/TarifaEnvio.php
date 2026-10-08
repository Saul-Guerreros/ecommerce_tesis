<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class TarifaEnvio extends Model
{
    protected $table = 'tarifa_envio';
    protected $primaryKey = 'tev_id';
    public $timestamps = false;

    protected $fillable = ['tev_sucid', 'tev_ciuid', 'tev_costo'];

    protected $casts = [
        'tev_id'     => 'integer',
        'tev_sucid'  => 'integer',
        'tev_ciuid'  => 'integer',
        'tev_costo'  => 'decimal:2',
        'tev_creado' => 'datetime',
    ];

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'tev_sucid', 'suc_id');
    }

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class, 'tev_ciuid', 'ciu_id');
    }
}