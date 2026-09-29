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
        'prd_habmin',
        'prd_habmaj',
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
        'prd_habmin'      => 'boolean',
        'prd_habmaj'      => 'boolean',
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

    // NOTA: ya no existe una relación imagenes() directa aquí. Las imágenes
    // viven en publicacion_web (img_pubid), no en producto: accedé a ellas
    // vía $producto->publicacionWeb?->imagenes (puede ser null si el
    // producto todavía no tiene ficha web).

    public function stock(): HasMany
    {
        return $this->hasMany(\App\Models\Compras\Stock::class, 'sto_prdid', 'prd_id');
    }

    /**
     * Stock disponible real: físico menos reservado, sumado en todos los
     * depósitos. Requiere que la relación stock() esté cargada (eager loading)
     * para no provocar N+1 al recorrer listados.
     *
     * Se centraliza acá porque el mismo cálculo lo necesitan VitrinaController
     * (vitrina y detalle), CarritoController (validación) y CarritoResource
     * (para que el frontend pueda topear el control de cantidad).
     */
    public function stockDisponible(): float
    {
        return (float) $this->stock->sum(
            fn ($s) => (float) $s->sto_cantid - (float) $s->sto_reserv
        );
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

    /** Opciones de financiación configuradas para este producto (cuotero). */
    public function configuracionesCuota(): HasMany
    {
        return $this->hasMany(\App\Models\Carrito\ConfiguracionCuota::class, 'cfc_prdid', 'prd_id');
    }

    // ------------------------------------------------------------------
    // Resolución de precio de venta
    //
    // ÚNICA fuente de verdad del precio: antes la regla estaba escrita en
    // tres lugares distintos (vitrina, carrito y cálculo de ofertas) con
    // tres fallbacks diferentes, y un producto sin precio web terminaba
    // mostrándose a un importe en el home y guardándose a 0 en el carrito.
    // Todo el que necesite saber a cuánto se vende este producto llama acá.
    // ------------------------------------------------------------------

    /** Se vende ÚNICAMENTE al por mayor (no tiene modalidad minorista). */
    public function soloMayorista(): bool
    {
        return (bool) $this->prd_habmaj && ! $this->prd_habmin;
    }

    /**
     * Cantidad mínima para acceder al precio mayorista: el override del
     * producto si lo tiene, o si no, el umbral general de la tienda.
     */
    public function umbralMayorista(): float
    {
        return (float) ($this->prd_canminmaj ?? ConfiguracionPrecio::actual()->cpr_canminmaj);
    }

    /**
     * Cantidad mínima que se puede comprar. Para un producto solo
     * mayorista es el umbral (por debajo de eso no hay precio aplicable);
     * para el resto, 1.
     */
    public function cantidadMinimaVenta(): float
    {
        return $this->soloMayorista() ? $this->umbralMayorista() : 1.0;
    }

    /**
     * Precio unitario que corresponde a una cantidad concreta. Es lo que
     * debe usar el carrito: nunca acepta un precio enviado por el cliente.
     *
     * Devuelve null cuando no hay precio aplicable — caso real: producto
     * solo mayorista y cantidad por debajo del umbral. El llamador debe
     * rechazar la operación, no asumir 0.
     */
    public function precioPara(float $cantidad): ?float
    {
        if ($this->prd_habmaj && $this->prd_pvtmay !== null && $cantidad >= $this->umbralMayorista()) {
            return (float) $this->prd_pvtmay;
        }

        if ($this->prd_habmin && $this->prd_pvtmin !== null) {
            return (float) $this->prd_pvtmin;
        }

        return null;
    }

    /**
     * IVA contenido en un monto que YA incluye IVA (prd_pvtmin/prd_pvtmay
     * salen de PriceService::precioFinal() con el IVA sumado adentro, no
     * aparte). Mismo criterio y misma fórmula que CompraService y
     * NotaCreditoService usan para las facturas de compra: tasa/(100+tasa)
     * → 10% = /11, 5% = /21, exenta = 0. Requiere tipoIva cargada
     * (Producto::with('tipoIva')) para no disparar una query N+1.
     */
    public function ivaIncluidoEn(float $monto): float
    {
        $tasa = (float) ($this->tipoIva->tiv_tasa ?? 0);

        return $tasa > 0 ? round($monto * $tasa / (100 + $tasa)) : 0.0;
    }

    /**
     * Precio a mostrar en la vitrina, donde todavía no hay cantidad
     * elegida: el minorista si el producto se vende al por menor, y si no
     * (solo mayorista) el mayorista, que el frontend acompaña con la
     * leyenda "desde X unidades".
     */
    public function precioVitrina(): ?float
    {
        if ($this->prd_habmin && $this->prd_pvtmin !== null) {
            return (float) $this->prd_pvtmin;
        }

        if ($this->prd_habmaj && $this->prd_pvtmay !== null) {
            return (float) $this->prd_pvtmay;
        }

        return null;
    }
}