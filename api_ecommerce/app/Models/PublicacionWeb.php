<?php

namespace App\Models;

use App\Models\product\EspecificacionProducto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Ficha pública ("storefront") de un producto: título y descripción para
 * la web, categoría, estado de publicación, imágenes y especificaciones.
 * Relación 1:1 con Producto (pub_prdid).
 */
class PublicacionWeb extends Model
{
    use HasFactory;

    protected $table = 'publicacion_web';

    protected $primaryKey = 'pub_id';

    public $timestamps = false;

    const CREATED_AT = 'pub_creado';
    const UPDATED_AT = null;

    // Estados de publicación
    const ESTADO_BORRADOR = 0;
    const ESTADO_PUBLICADO = 1;
    const ESTADO_ARCHIVADO = 2;

    protected $fillable = [
        'pub_prdid',
        'pub_catid',
        'pub_titulo',
        'pub_prevta',
        'pub_slug',
        'pub_descwb',
        'pub_meta_titulo',
        'pub_meta_descripcion',
        'pub_estado',
        'pub_pocstk_umbral',
    ];

    protected $casts = [
        'pub_estado' => 'integer',
        'pub_pocstk_umbral' => 'integer',
        'pub_prevta' => 'decimal:2',
        'pub_creado' => 'datetime',
        'pub_actualizado' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        // pub_actualizado: registra cuándo se editó por última vez la ficha
        // (antes solo existía pub_creado, sin auditoría de ediciones).
        static::saving(function (self $publicacion) {
            $publicacion->pub_actualizado = now();

            // pub_slug: se autogenera desde pub_titulo si no vino uno
            // explícito (o si vino vacío), garantizando unicidad.
            if (empty($publicacion->pub_slug) && ! empty($publicacion->pub_titulo)) {
                $publicacion->pub_slug = $publicacion->generarSlugUnico($publicacion->pub_titulo);
            }
        });
    }

    private function generarSlugUnico(string $titulo): string
    {
        $base = Str::slug($titulo) ?: 'producto';
        $slug = $base;
        $sufijo = 1;

        while (
            self::where('pub_slug', $slug)
                ->when($this->pub_id, fn ($q) => $q->where('pub_id', '!=', $this->pub_id))
                ->exists()
        ) {
            $slug = "{$base}-{$sufijo}";
            $sufijo++;
        }

        return $slug;
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'pub_prdid', 'prd_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'pub_catid', 'cat_id');
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(ImagenProducto::class, 'img_pubid', 'pub_id')
            ->orderBy('img_orden');
    }

    public function especificaciones(): HasMany
    {
        return $this->hasMany(EspecificacionProducto::class, 'esp_pubid', 'pub_id');
    }

    public function estaPublicada(): bool
    {
        return (int) $this->pub_estado === self::ESTADO_PUBLICADO;
    }
	
	// ── Agregar dentro de la clase PublicacionWeb ──
 
public function vitrinas(): HasMany
{
    return $this->hasMany(VitrinaProducto::class, 'vip_pubid', 'pub_id');
}
 
/**
 * Orden de esta publicación dentro de una sección de vitrina, o null
 * si no está activa en esa sección. Útil para no tener que buscar a
 * mano en la colección `vitrinas` cargada.
 */
public function ordenEnSeccion(string $seccion): ?int
{
    return $this->vitrinas->firstWhere('vip_seccion', $seccion)?->vip_orden;
}
 
// NOTA: si scopePublicadas / scopeDestacadas ya existen en algún otro
// lugar de tu proyecto (otro trait, otra versión de este archivo), NO
// agregues estos de nuevo: PHP no permite declarar el mismo método dos
// veces y tirará un fatal error. Si no existen todavía, agregalos así:
 
public function scopePublicadas(Builder $query): Builder
{
    // Un repuesto de taller nunca se muestra en la tienda, aunque la fila
    // de publicación existiera (defensa en profundidad).
    return $query->where('pub_estado', self::ESTADO_PUBLICADO)
        ->whereHas('producto', fn (Builder $p) => $p->where('prd_repues', 0));
}
 
public function scopeDestacadas(Builder $query): Builder
{
    return $this->scopeEnSeccion($query, VitrinaProducto::SECCION_FEATURED);
}
 
public function scopeTrending(Builder $query): Builder
{
    return $this->scopeEnSeccion($query, VitrinaProducto::SECCION_TRENDING);
}
 
public function scopeSelling(Builder $query): Builder
{
    return $this->scopeEnSeccion($query, VitrinaProducto::SECCION_SELLING);
}
 
/**
 * Filtra publicaciones que tienen una fila en vitrina_producto para
 * $seccion, ordenadas por vip_orden ascendente (1 = primero).
 */
private function scopeEnSeccion(Builder $query, string $seccion): Builder
{
    return $query
        ->whereHas('vitrinas', fn (Builder $q) => $q->where('vip_seccion', $seccion))
        ->with(['vitrinas' => fn ($q) => $q->where('vip_seccion', $seccion)])
        ->orderBy(
            VitrinaProducto::select('vip_orden')
                ->whereColumn('vip_pubid', 'publicacion_web.pub_id')
                ->where('vip_seccion', $seccion)
        );
}
}
