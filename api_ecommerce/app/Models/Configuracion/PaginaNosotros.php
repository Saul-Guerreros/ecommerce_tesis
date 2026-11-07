<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PaginaNosotros extends Model
{
    protected $table      = 'pagina_nosotros';
    protected $primaryKey = 'pno_id';

    const CREATED_AT = null;
    const UPDATED_AT = 'pno_actualizado';

    /** Clave de caché de la respuesta pública (se invalida al guardar). */
    public const CACHE_PUBLICA = 'tienda.pagina_nosotros';

    public const MAX_VALORES = 6;
    public const MAX_CIFRAS  = 4;

    /**
     * Íconos de UIkit permitidos para los valores. Lista blanca: el admin
     * elige de un select y el backend rechaza cualquier otro texto.
     * Mantener en sincronía con ICONOS_NOSOTROS del admin.
     */
    public const ICONOS = [
        'star', 'heart', 'happy', 'users', 'check', 'bolt', 'lifesaver',
        'future', 'clock', 'desktop', 'cog', 'code', 'cart', 'tag', 'world', 'location',
    ];

    protected $fillable = [
        'pno_titulo', 'pno_subtitulo', 'pno_historia', 'pno_mision', 'pno_vision',
        'pno_valores', 'pno_cifras', 'pno_imagen', 'pno_publicado', 'pno_usuid',
    ];

    protected $casts = [
        'pno_id'          => 'integer',
        'pno_valores'     => 'array',
        'pno_cifras'      => 'array',
        'pno_publicado'   => 'boolean',
        'pno_usuid'       => 'integer',
        'pno_actualizado' => 'datetime',
    ];

    protected static function booted(): void
    {
        $limpiar = fn () => Cache::forget(self::CACHE_PUBLICA);
        static::saved($limpiar);
        static::deleted($limpiar);
    }

    /** Devuelve la única fila; la crea vacía si todavía no existe. */
    public static function actual(): self
    {
        return static::query()->first()
            ?? static::create(['pno_titulo' => 'Sobre nosotros', 'pno_valores' => [], 'pno_cifras' => []]);
    }

       public function imagenUrl(): ?string
    {
        // Igual que SliderController: usa APP_URL, no el host del request.
        return $this->pno_imagen
            ? rtrim(config('app.url'), '/') . '/storage/' . $this->pno_imagen
            : null;
    }
}