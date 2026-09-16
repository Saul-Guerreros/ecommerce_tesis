<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionPrecio extends Model
{
    protected $table = 'configuracion_precio';
    protected $primaryKey = 'cpr_id';
    public $timestamps = false;

    protected $fillable = [
        'cpr_marmin',
        'cpr_marmaj',
        'cpr_canminmaj',
    ];

    protected $casts = [
        'cpr_id'        => 'integer',
        'cpr_marmin'    => 'decimal:4',
        'cpr_marmaj'    => 'decimal:4',
        'cpr_canminmaj' => 'decimal:4',
        'cpr_creado'    => 'datetime',
    ];

    /**
     * Devuelve la única fila de configuración. Lanza excepción clara si
     * el seeder no corrió, en vez de fallar en cascada dentro de
     * PriceService con un error confuso de "null given".
     */
    public static function actual(): self
    {
        return static::query()->firstOrFail();
    }
}
