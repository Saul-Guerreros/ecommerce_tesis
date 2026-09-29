<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionTienda extends Model
{
    protected $table = 'configuracion_tienda';
    protected $primaryKey = 'ctn_id';
    public $timestamps = false;

    protected $fillable = [
        'ctn_pocstk_activo',
        'ctn_pocstk_umbral',
    ];

    protected $casts = [
        'ctn_id'            => 'integer',
        'ctn_pocstk_activo' => 'boolean',
        'ctn_pocstk_umbral' => 'integer',
        'ctn_creado'        => 'datetime',
    ];

    /** Valores con los que se crea la fila si todavía no existe. */
    public const VALORES_INICIALES = [
        'ctn_pocstk_activo' => true,
        'ctn_pocstk_umbral' => 5,
    ];

    /** Instancia memoizada durante el request (ver actual()). */
    private static ?self $memo = null;

    /**
     * Devuelve la única fila de configuración.
     *
     * A diferencia de ConfiguracionPrecio::actual(), acá NO se lanza
     * excepción si la fila no existe: se crea con los valores iniciales.
     * Esta configuración solo decide qué avisos se muestran, y el Detalle
     * del Producto (público) no debería caerse con un 500 porque el
     * seeder no corrió. Para precios sí se justifica fallar fuerte,
     * porque un valor inventado cobraría mal.
     *
     * Se memoiza igual que ConfiguracionPrecio: se consulta una vez por
     * request y update() modifica esa misma instancia.
     */
    public static function actual(): self
    {
        return static::$memo ??= static::query()->first()
            ?? static::create(self::VALORES_INICIALES);
    }
}