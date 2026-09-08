<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class TipoComprobante extends Model
{
    protected $table      = 'tipo_comprobante';
    protected $primaryKey = 'tco_id';
    public    $timestamps = false;

    protected $fillable = ['tco_codigo', 'tco_descri', 'tco_ambito', 'tco_reqtim', 'tco_afecstk', 'tco_estado'];

    protected $casts = [
        'tco_id'      => 'integer',
        'tco_reqtim'  => 'integer',
        'tco_afecstk' => 'integer',
        'tco_estado'  => 'integer',
    ];
}