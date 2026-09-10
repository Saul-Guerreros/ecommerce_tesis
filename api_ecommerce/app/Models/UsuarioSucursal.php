<?php

namespace App\Models;

use App\Models\Configuracion\Sucursal;
use Illuminate\Database\Eloquent\Model;

class UsuarioSucursal extends Model
{
    protected $table      = 'usuario_sucursal';
    protected $primaryKey = 'uss_id';
    public    $timestamps = false;

    protected $fillable = ['uss_usuid', 'uss_sucid', 'uss_princi', 'uss_estado'];

    protected $casts = [
        'uss_id'     => 'integer',
        'uss_usuid'  => 'integer',
        'uss_sucid'  => 'integer',
        'uss_princi' => 'integer',
        'uss_estado' => 'integer',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'uss_usuid', 'usu_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'uss_sucid', 'suc_id');
    }
}