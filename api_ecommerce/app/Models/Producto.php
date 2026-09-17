<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Compras\TipoIva;
use App\Models\Compras\UnidadMedida;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'producto';
    protected $primaryKey = 'prd_id';
    public $timestamps = false;

    // La tabla solo tiene prd_creado, no tiene updated_at
    const CREATED_AT = 'prd_creado';
    const UPDATED_AT = null;

    protected $fillable = [
        'prd_padre_id',
        'prd_marid',
        'prd_tivid',
        'prd_umdid',
        'prd_codinv',
        'prd_pvtmin',
        'prd_pvtmay',
        'prd_marmin',
        'prd_marmaj',
        'prd_canminmaj',
        'prd_descri',
        'prd_stkmin',
        'prd_afestk',
        'prd_estado',
    ];

    protected $casts = [
        'prd_id'          => 'integer',
        'prd_marid'       => 'integer',
        'prd_tivid'       => 'integer',
        'prd_umdid'       => 'integer',
        'prd_pvtmin'      => 'decimal:2',
        'prd_pvtmay'      => 'decimal:2',
        'prd_marmin'      => 'decimal:4',
        'prd_marmaj'      => 'decimal:4',
        'prd_canminmaj'   => 'decimal:4',
        'prd_stkmin'      => 'decimal:4',
        'prd_afestk'      => 'integer',
        'prd_estado'      => 'integer',
        'prd_creado'      => 'datetime',
    ];

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'prd_marid', 'mar_id');
    }

    public function tipoIva(): BelongsTo
    {
        return $this->belongsTo(TipoIva::class, 'prd_tivid', 'tiv_id');
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'prd_umdid', 'umd_id');
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(ImagenProducto::class, 'img_prdid', 'prd_id');
    }

    public function stock(): HasMany
    {
        return $this->hasMany(\App\Models\Compras\Stock::class, 'sto_prdid', 'prd_id');
    }


    /**
     * Ficha web (categoría, título, descripción, imágenes, estado de
     * publicación). Puede no existir todavía: un producto recién cargado
     * en inventario no está necesariamente publicado en la tienda.
     */
    public function publicacionWeb(): HasOne
    {
        return $this->hasOne(PublicacionWeb::class, 'pub_prdid', 'prd_id');
    }

    /** El producto "modelo base" del que este es una variante (color, edición, etc.) */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'prd_padre_id', 'prd_id');
    }

    /** Otros productos que apuntan a ESTE como su modelo base */
    public function variantes(): HasMany
    {
        return $this->hasMany(Producto::class, 'prd_padre_id', 'prd_id');
    }
    // Para ProductoEquivalencias
    public function equivalencias(): HasMany
    {
        return $this->hasMany(\App\Models\Compras\ProductoEquivalencia::class, 'peq_prdid', 'prd_id');
    }
}