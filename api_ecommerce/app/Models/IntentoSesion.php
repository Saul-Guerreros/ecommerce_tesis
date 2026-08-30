<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntentoSesion extends Model
{
    protected $table = 'intento_sesion';
    protected $primaryKey = 'ise_id';
    public $timestamps = false;

    protected $fillable = [
        'ise_usuid',
        'ise_logusa',
        'ise_result',
        'ise_obs',
        'ise_iporig',
        'ise_userag',
    ];

    protected $casts = [
        'ise_creado' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'ise_usuid', 'usu_id');
    }
}
