<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Código de un solo uso, entregado en el mostrador, para crear la cuenta web sobre una ficha existente. */
class VinculacionCuenta extends Model
{
    public const VIGENTE = 'VIGENTE';
    public const USADO   = 'USADO';
    public const ANULADO = 'ANULADO';

    protected $table = 'vinculacion_cuenta';
    protected $primaryKey = 'vin_id';
    public $timestamps = false;

    protected $fillable = [
        'vin_perid', 'vin_codigo', 'vin_fecexp', 'vin_intentos', 'vin_estado', 'vin_fecuso',
    ];

    protected $hidden = ['vin_codigo'];

    protected $casts = [
        'vin_codigo' => 'encrypted',
        'vin_fecexp' => 'datetime',
        'vin_fecuso' => 'datetime',
    ];
}