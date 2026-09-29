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

    /** Instancia memoizada durante el request (ver actual()). */
    private static ?self $memo = null;

    /**
     * Devuelve la única fila de configuración. Lanza excepción clara si
     * el seeder no corrió, en vez de fallar en cascada dentro de
     * PriceService con un error confuso de "null given".
     *
     * Se memoiza porque se consulta una vez POR PRODUCTO en varios
     * recorridos (margenEfectivo(), umbralMayorista()): sin esto, un
     * listado de 70 productos dispara 70 consultas idénticas.
     *
     * Devolver siempre la MISMA instancia también evita que el cache
     * quede viejo: ConfiguracionPrecioController::update() modifica y
     * guarda ese mismo objeto, así que el valor memoizado ya refleja
     * el cambio cuando PriceService recalcula a continuación.
     */
    public static function actual(): self
    {
        return static::$memo ??= static::query()->firstOrFail();
    }
}