<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Discount\Discount;
use App\Models\Producto;
use App\Models\Product\EspecificacionProducto;
use App\Models\PublicacionWeb;
use App\Models\Slider;
use App\Services\Cuota\CuotaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Vitrina de Home: resuelve qué productos aparecen en cada sección de la
 * página principal del ecommerce.
 *
 * Mecanismo por sección (ver análisis previo):
 * - Nuevos (New Arrivals):        automático, por fecha de publicación.
 * - Destacados (Featured):        manual — se marca desde el propio
 *   formulario de "Publicación Web" de cada producto (campo
 *   pub_destacado_orden), no desde una pantalla aparte.
 * - En oferta (Discount Products): automático, reutiliza `discounts` +
 *   discount_products/categories/brands (ya existían, no son nuevos).
 * - Oferta del día (Deal of the Day): mismo mecanismo que "en oferta",
 *   filtrado a descuentos type_campaing = 2 (flash).
 * - Más vendidos (Selling Products): NO implementado a propósito. Requiere
 *   datos reales de ventas que todavía no existen en el sistema (no hay
 *   módulo de Pedidos/Ventas). Mostrar un "top ventas" sin ventas reales
 *   sería un dato falso, así que esta sección queda pendiente hasta que
 *   exista esa información.
 */
class VitrinaController extends Controller
{
    /**
     * El cuotero de cada tarjeta se calcula en el backend para que el home
     * y el detalle muestren siempre los mismos importes. El service se
     * inyecta por constructor (el container de Laravel lo resuelve solo) y
     * cachea los planes durante el request, así formatear ~70 productos no
     * dispara ~70 consultas a plan_cuota.
     */
    public function __construct(private readonly CuotaService $cuotaService)
    {
    }

    // GET /api/tienda/home — público, consumido por el frontend del ecommerce (sin auth)
    public function home(Request $request): JsonResponse
    {
        $limite = min((int) $request->query('limite', 12), 24);

    // Descuentos activos, resueltos una sola vez por request y aplicados a
    // TODAS las secciones (no solo a en_oferta / oferta_del_dia), para que un
    // mismo producto muestre el mismo precio en cualquier lugar de la tienda.
    $descuentos = $this->descuentosActivosPorProducto();
 
    $nuevos = PublicacionWeb::publicadas()
        ->with(['producto.marca', 'producto.stock', 'categoria', 'imagenes'])
        ->orderByDesc('pub_creado')
        ->limit($limite)
        ->get()
        ->map(fn (PublicacionWeb $pub) => $this->formatearConOferta($pub, $descuentos));
 
    $destacados = PublicacionWeb::publicadas()
        ->destacadas()
        ->with(['producto.marca', 'producto.stock', 'categoria', 'imagenes'])
        ->limit($limite)
        ->get()
        ->map(fn (PublicacionWeb $pub) => $this->formatearConOferta($pub, $descuentos));
 
    $trending = PublicacionWeb::publicadas()
        ->trending()
        ->with(['producto.marca', 'producto.stock', 'categoria', 'imagenes'])
        ->limit($limite)
        ->get()
        ->map(fn (PublicacionWeb $pub) => $this->formatearConOferta($pub, $descuentos));
 
    $masVendidos = PublicacionWeb::publicadas()
        ->selling()
        ->with(['producto.marca', 'producto.stock', 'categoria', 'imagenes'])
        ->limit($limite)
        ->get()
        ->map(fn (PublicacionWeb $pub) => $this->formatearConOferta($pub, $descuentos));
 
    [$enOferta, $ofertaDelDia] = $this->resolverOfertas($limite, $descuentos);
 
    return response()->json([
        'data' => [
            'nuevos' => $nuevos,
            'destacados' => $destacados,
            'trending' => $trending,
            'mas_vendidos' => $masVendidos,
            'en_oferta' => $enOferta,
            'oferta_del_dia' => $ofertaDelDia,
        ],
    ]);
    }

    // GET /api/tienda/sliders — público, banners del home (hero + promo + spotlight)
    public function sliders(): JsonResponse
    {
        $sliders = Slider::where('sld_estado', 1)
            ->orderBy('sld_orden')
            ->orderBy('sld_id')
            ->get();

        $secundarios = $sliders->where('sld_tipo', 2);

        return response()->json([
            'data' => [
                // sld_tipo 1 = Banner principal (hero uk-slideshow)
                'principales' => $sliders->where('sld_tipo', 1)->values()
                    ->map(fn (Slider $s) => $this->formatearSlider($s)),
                // sld_tipo 2 = Banner secundario, repartidos en 2 carruseles
                // independientes según sld_posicion (1 = izquierda, 2 = derecha)
                'secundarios_izquierda' => $secundarios->where('sld_posicion', 1)->values()
                    ->map(fn (Slider $s) => $this->formatearSlider($s)),
                'secundarios_derecha' => $secundarios->where('sld_posicion', 2)->values()
                    ->map(fn (Slider $s) => $this->formatearSlider($s)),
                // sld_tipo 3 = Banner terciario (Product spotlight), un solo
                // carrusel — no usa sld_posicion.
                'terciarios' => $sliders->where('sld_tipo', 3)->values()
                    ->map(fn (Slider $s) => $this->formatearSlider($s)),
            ],
        ]);
    }

    // GET /api/ecommerce/menus — árbol de categorías para el mega-menú del header
    public function menus(): JsonResponse
    {
        $categoriasMenu = Categoria::where('cat_nivel', 1)
            ->with(['hijas' => fn ($q) => $q->orderBy('cat_nombre')])
            ->orderBy('cat_nombre')
            ->get()
            ->map(fn (Categoria $cat) => [
                'id' => $cat->cat_id,
                'name' => $cat->cat_nombre,
                'categories' => $cat->hijas->map(fn (Categoria $h) => [
                    'id' => $h->cat_id,
                    'name' => $h->cat_nombre,
                ]),
            ]);

        return response()->json(['categories_menus' => $categoriasMenu]);
    }

    // GET /api/productos — catálogo público paginado (ProductoPublicoService del storefront)
    public function productos(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 8));

        $productos = Producto::query()
            ->whereHas('publicacionWeb', fn ($q) => $q->publicadas())
            // `stock` se precarga porque ProductoResource deriva el inventario
            // de esa relación (prd_stock ya no existe en la tabla producto).
            ->with(['publicacionWeb.categoria', 'publicacionWeb.imagenes', 'stock'])
            ->orderByDesc('prd_id')
            ->paginate($perPage);

        return ProductoPublicoResource::collection($productos)->response();
    }

    // ---------------------------------------------------------------
    // Resolución de ofertas activas (reutiliza el módulo de Discounts existente)
    // ---------------------------------------------------------------

    /**
     * @return array{0: Collection, 1: Collection} [en_oferta, oferta_del_dia]
     */
    private function resolverOfertas(int $limite, Collection $descuentosPorProducto): array
    {
        if ($descuentosPorProducto->isEmpty()) {
            return [collect(), collect()];
        }

        $formateados = PublicacionWeb::publicadas()
            ->whereIn('pub_prdid', $descuentosPorProducto->keys())
            ->with(['producto.marca', 'producto.stock', 'categoria', 'imagenes'])
            ->get()
            ->map(fn (PublicacionWeb $pub) => $this->formatearConOferta($pub, $descuentosPorProducto));

        $enOferta = $formateados->sortBy('precio_final')->take($limite)->values();

        $ofertaDelDia = $formateados
            ->filter(fn (array $p) => $p['es_flash'])
            ->sortBy('oferta_fin')
            ->take(8)
            ->values();

        return [$enOferta, $ofertaDelDia];
    }

    /** @return Collection<int, Collection<int, Discount>> prd_id => descuentos activos que le aplican */
    private function descuentosActivosPorProducto(): Collection
    {
        $hoy = now()->toDateString();

        $descuentosActivos = Discount::query()
            ->where('state', 1)
            ->whereDate('start_date', '<=', $hoy)
            ->whereDate('end_date', '>=', $hoy)
            ->with(['products', 'categories', 'brands'])
            ->get();

        $mapa = collect();

        foreach ($descuentosActivos as $descuento) {
            // discount_type: 1 = productos, 2 = categorias, 3 = marcas (alcance del descuento)
            $productoIds = match ((int) $descuento->discount_type) {
                1 => $descuento->products->pluck('product_id'),
                2 => Producto::whereHas(
                    'publicacionWeb',
                    fn ($q) => $q->whereIn('pub_catid', $descuento->categories->pluck('categorie_id'))
                )->pluck('prd_id'),
                3 => Producto::whereIn('prd_marid', $descuento->brands->pluck('brand_id'))->pluck('prd_id'),
                default => collect(),
            };

            foreach ($productoIds as $productoId) {
                if (! $mapa->has($productoId)) {
                    $mapa->put($productoId, collect());
                }
                $mapa->get($productoId)->push($descuento);
            }
        }

        return $mapa;
    }

    /**
     * Mejor descuento activo para una publicación (el que deja el precio final
     * más bajo), o null si no le aplica ninguno.
     *
     * Si varios descuentos activos aplican al mismo producto (ej. uno por
     * categoría y otro puntual), se usa el que le conviene más al cliente —
     * así se evita mostrar un precio distinto según qué regla "gane".
     *
     * @param  Collection<int, Collection<int, Discount>>  $descuentosPorProducto
     * @return array{descuento: Discount, precio_final: float}|null
     */
    private function mejorOfertaPara(PublicacionWeb $pub, Collection $descuentosPorProducto): ?array
    {
        $descuentos = $descuentosPorProducto->get($pub->pub_prdid);

        if (! $descuentos || $descuentos->isEmpty()) {
            return null;
        }

        $precioBase = (float) ($pub->pub_prevta ?? $pub->producto->prd_precio);

        return $descuentos
            ->map(fn (Discount $descuento) => [
                'descuento' => $descuento,
                'precio_final' => $this->calcularPrecioFinal($precioBase, $descuento),
            ])
            ->sortBy('precio_final')
            ->first();
    }

    /**
     * Atajo: formatea la publicación aplicándole su mejor descuento activo
     * (si tiene). Es lo que deben usar TODAS las salidas públicas de producto
     * (home, detalle, relacionados) para que el precio sea siempre coherente.
     *
     * @param  Collection<int, Collection<int, Discount>>  $descuentosPorProducto
     */
    private function formatearConOferta(PublicacionWeb $pub, Collection $descuentosPorProducto): array
    {
        return $this->formatearProducto($pub, $this->mejorOfertaPara($pub, $descuentosPorProducto));
    }

    private function calcularPrecioFinal(float $precioBase, Discount $descuento): float
    {
        // type_discount: 1 = porcentaje, 2 = monto fijo (forma en que se aplica el descuento)
        if ((int) $descuento->type_discount === 1) {
            return max(0, round($precioBase * (1 - ((float) $descuento->discount / 100)), 2));
        }

        return max(0, round($precioBase - (float) $descuento->discount, 2));
    }

    // ---------------------------------------------------------------
    // Formato uniforme de salida (mismo shape en todas las secciones)
    // ---------------------------------------------------------------

    private function formatearProducto(PublicacionWeb $pub, ?array $mejorOferta = null): array
    {
        $producto = $pub->producto;
        $precioOriginal = (float) ($pub->pub_prevta ?? $producto->prd_pvtmin);
        $precioFinal = $precioOriginal;
        $descuentoPct = null;
        $ofertaFin = null;
        $esFlash = false;

        if ($mejorOferta) {
            $precioFinal = $mejorOferta['precio_final'];
            $descuentoPct = $precioOriginal > 0
                ? (int) round((1 - ($precioFinal / $precioOriginal)) * 100)
                : null;
            // end_date en `discounts` es DATE (sin hora): se trata como fin
            // del día para que el countdown del frontend tenga un instante
            // real al que apuntar, en vez de mostrar 00:00:00 desde temprano.
            $ofertaFin = Carbon::parse($mejorOferta['descuento']->end_date)->endOfDay()->toIso8601String();
            $esFlash = (int) $mejorOferta['descuento']->type_campaing === 2;
        }

        return [
            'pub_id' => $pub->pub_id,
            'prd_id' => $producto->prd_id,
            'sku' => $producto->prd_codinv,
            'titulo' => $pub->pub_titulo,
            'slug' => $pub->pub_slug,
            'categoria' => $pub->categoria?->cat_nombre,
            'marca' => $producto->marca?->mar_descri,
            'imagen' => $this->imagenPrincipal($pub),
            'precio_original' => $precioOriginal,
            'precio_final' => $precioFinal,
            // Las cuotas se calculan sobre el precio YA con descuento: es lo
            // que el cliente va a financiar realmente.
            'cuotas' => $this->cuotaService->calcularOpciones($precioFinal),
            'descuento_pct' => $descuentoPct,
            'oferta_fin' => $ofertaFin,
            'es_flash' => $esFlash,
            // Todavía no existe un módulo de reseñas: se manda null a
            // propósito para que el frontend NO invente estrellas ni
            // cantidad de reseñas falsas.
            'rating' => null,
            // Inventario real: ya no existe prd_stock (ver migración
            // adapt_producto_table_to_der), así que se suma la tabla stock.
            // controla_stock = false significa "no se controla stock para
            // este producto" (prd_afestk = 0), no "sin datos".
            'stock' => $this->stockDisponible($producto),
            'controla_stock' => (bool) $producto->prd_afestk,
        ];
    }

    /**
     * Suma de stock físico menos reservas, en todos los depósitos.
     * prd_stock ya no existe en producto (migración
     * adapt_producto_table_to_der): el inventario se deriva de la
     * relación Producto::stock() -> Compras\Stock.
     */
    private function stockDisponible(Producto $producto): float
    {
        return (float) $producto->stock->sum(
            fn ($s) => (float) $s->sto_cantid - (float) $s->sto_reserv
        );
    }

    private function imagenPrincipal(PublicacionWeb $pub): ?string
    {
        $imagen = $pub->imagenes->firstWhere('img_princi', 1) ?? $pub->imagenes->first();

        return $imagen ? asset('storage/'.$imagen->img_url) : null;
    }

    private function formatearSlider(Slider $slider): array
    {
        return [
            'sld_id' => $slider->sld_id,
            'titulo' => $slider->sld_titulo,
            'subtitulo' => $slider->sld_subtitulo,
            'label' => $slider->sld_label,
            'enlace' => $slider->sld_enlace,
            'color' => $slider->sld_color,
            'imagen' => $slider->sld_imagen ? asset('storage/'.$slider->sld_imagen) : null,
        ];
    }

    // ---------------------------------------------------------------
    // Productos relacionados ("También te puede gustar")
    // ---------------------------------------------------------------

    /** Cuántos productos se muestran en "También te puede gustar". */
    private const RELACIONADOS_LIMITE = 8;

    /**
     * Relacionados como en un ecommerce real:
     *
     *  1. EXCLUYE la familia completa del producto (el modelo base + todas sus
     *     variantes). Las variantes ya se ofrecen aparte (colores_disponibles);
     *     "relacionados" es para descubrir OTROS productos.
     *  2. Candidatos: publicaciones publicadas de la misma categoría, sus
     *     subcategorías, sus hermanas y la categoría padre. Si no alcanzan para
     *     llenar la lista, se completa con el resto del catálogo publicado.
     *  3. Ordena por afinidad (ver puntuarRelacionado()): misma categoría >
     *     categorías afines > resto; luego misma marca, precio parecido y
     *     productos de vitrina; los sin stock quedan al final.
     *  4. Diversifica: máximo UN producto por familia (si dos variantes de otro
     *     modelo califican, se muestra solo la mejor).
     *
     * @return Collection<int, array> ya formateados (con descuento aplicado)
     */
    private function resolverRelacionados(
        PublicacionWeb $pub,
        Producto $producto,
        Collection $descuentos,
        int $limite = self::RELACIONADOS_LIMITE
    ): Collection {
        $familiaId = $producto->prd_padre_id ?? $producto->prd_id;
        $categoriaId = $pub->pub_catid ? (int) $pub->pub_catid : null;

        // Categorías afines: la propia, sus hijas, sus hermanas y la categoría padre.
        $categoriasAfines = [];
        if ($categoriaId) {
            $padreId = $pub->categoria?->cat_idpadr;

            $categoriasAfines = Categoria::query()
                ->where('cat_id', $categoriaId)
                ->orWhere('cat_idpadr', $categoriaId)
                ->when($padreId, fn ($q) => $q
                    ->orWhere('cat_idpadr', $padreId)
                    ->orWhere('cat_id', $padreId))
                ->pluck('cat_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        // Base común: publicadas, y fuera toda la familia del producto actual.
        $candidatas = fn () => PublicacionWeb::publicadas()
            ->where('pub_id', '!=', $pub->pub_id)
            ->whereHas('producto', fn ($q) => $q
                ->where('prd_id', '!=', $familiaId)
                ->where(fn ($w) => $w
                    ->whereNull('prd_padre_id')
                    ->orWhere('prd_padre_id', '!=', $familiaId)))
            ->with(['producto.marca', 'producto.stock', 'categoria', 'imagenes', 'vitrinas'])
            ->orderByDesc('pub_id');

        $familiaDe = fn (PublicacionWeb $p) => $p->producto->prd_padre_id ?? $p->producto->prd_id;

        $pool = $categoriasAfines
            ? $candidatas()->whereIn('pub_catid', $categoriasAfines)->limit(60)->get()
            : collect();

        // Si la categoría no aporta suficientes FAMILIAS distintas, se completa
        // con el resto del catálogo (así la sección nunca queda vacía ni corta
        // en una categoría chica).
        if ($pool->map($familiaDe)->unique()->count() < $limite) {
            $relleno = $candidatas()
                ->whereNotIn('pub_id', $pool->pluck('pub_id'))
                ->limit(60)
                ->get();

            $pool = $pool->concat($relleno);
        }

        return $pool
            ->map(fn (PublicacionWeb $c) => [
                'pub' => $c,
                'puntos' => $this->puntuarRelacionado($c, $pub, $producto, $categoriaId, $categoriasAfines),
            ])
            // Mayor puntaje primero; a igualdad, la publicación más nueva.
            ->sort(fn (array $a, array $b) => [$b['puntos'], $b['pub']->pub_id] <=> [$a['puntos'], $a['pub']->pub_id])
            ->unique(fn (array $x) => $familiaDe($x['pub']))
            ->take($limite)
            ->map(fn (array $x) => $this->formatearConOferta($x['pub'], $descuentos))
            ->values();
    }

    /**
     * Afinidad de un candidato con el producto que se está viendo.
     * Los pesos hacen que la categoría domine (100 vs 50) y que marca, precio y
     * vitrina solo desempaten dentro de cada nivel; sin stock siempre al final.
     */
    private function puntuarRelacionado(
        PublicacionWeb $candidata,
        PublicacionWeb $actual,
        Producto $productoActual,
        ?int $categoriaId,
        array $categoriasAfines
    ): float {
        $puntos = 0.0;
        $categoriaCandidata = $candidata->pub_catid ? (int) $candidata->pub_catid : null;

        if ($categoriaId && $categoriaCandidata === $categoriaId) {
            $puntos += 100;
        } elseif ($categoriaCandidata && in_array($categoriaCandidata, $categoriasAfines, true)) {
            $puntos += 50;
        }

        // Misma marca.
        if ($productoActual->prd_marid && (int) $candidata->producto->prd_marid === (int) $productoActual->prd_marid) {
            $puntos += 20;
        }

        // Precio parecido: 1.0 si cuestan igual, tiende a 0 mientras más se alejan.
        $a = (float) $actual->pub_prevta;
        $b = (float) $candidata->pub_prevta;
        if ($a > 0 && $b > 0) {
            $puntos += 20 * (min($a, $b) / max($a, $b));
        }

        // Curado por el admin en alguna sección de vitrina.
        if ($candidata->vitrinas->isNotEmpty()) {
            $puntos += 5;
        }

        // Sin stock (solo si el producto controla stock): siempre al final.
        if ($candidata->producto->prd_afestk && $this->stockDisponible($candidata->producto) <= 0) {
            $puntos -= 200;
        }

        return $puntos;
    }

   // GET /api/tienda/producto/{slug} — público
    public function detalle(string $slug): JsonResponse
    {
        $pub = PublicacionWeb::publicadas()
            ->where('pub_slug', $slug)
            ->with(['producto.marca', 'producto.stock.deposito.sucursal', 'producto.padre.publicacionWeb', 'categoria', 'imagenes'])
            ->firstOrFail();

        $producto = $pub->producto;

        // Mismo mecanismo de descuentos que el home: sin esto el detalle
        // mostraba siempre el precio de lista aunque la campaña estuviera activa.
        $descuentos = $this->descuentosActivosPorProducto();

        $especificaciones = EspecificacionProducto::with(['atributo', 'propiedad'])
            ->where('esp_pubid', $pub->pub_id)
            ->get()
            ->map(fn ($e) => [
                'atributo' => $e->atributo?->atb_nombre,
                'valor' => $e->esp_valibr
                    ?? $e->esp_valnum
                    ?? $e->propiedad?->prp_nombre,
            ]);

        // "Otros colores": el propio modelo base + todos sus hijos, salvo este mismo producto
        $grupoId = $producto->prd_padre_id ?? $producto->prd_id;

        $coloresDisponibles = Producto::query()
            ->where(fn ($q) => $q->where('prd_id', $grupoId)->orWhere('prd_padre_id', $grupoId))
            ->where('prd_id', '!=', $producto->prd_id)
            ->whereHas('publicacionWeb', fn ($q) => $q->publicadas())
            ->with('publicacionWeb.imagenes')
            ->get()
            ->map(fn (Producto $p) => [
                'slug' => $p->publicacionWeb->pub_slug,
                'titulo' => $p->publicacionWeb->pub_titulo,
                'imagen' => $this->imagenPrincipal($p->publicacionWeb),
            ]);

        // Galería completa (no solo la principal): la relación imagenes()
        // ya viene ordenada por img_orden.
        $galeria = $pub->imagenes
            ->map(fn ($img) => asset('storage/'.$img->img_url))
            ->values();

        // Retiro en sucursal: nombres reales de las sucursales que HOY
        // tienen stock disponible de este producto (cantidad - reservado > 0).
        $sucursalesPickup = $producto->stock
            ->filter(fn ($s) => ((float) $s->sto_cantid - (float) $s->sto_reserv) > 0)
            ->map(fn ($s) => $s->deposito?->sucursal?->suc_nombre)
            ->filter()
            ->unique()
            ->values();

        // Relacionados: otros productos afines (ver resolverRelacionados()).
        $relacionados = $this->resolverRelacionados($pub, $producto, $descuentos);

        return response()->json([
            'data' => [
                'producto' => $this->formatearConOferta($pub, $descuentos),
                // pub_descwb: puede venir null si no se completó todavía.
                'descripcion' => $pub->pub_descwb,
                'galeria' => $galeria,
                'especificaciones' => $especificaciones,
                'colores_disponibles' => $coloresDisponibles,
                'relacionados' => $relacionados,
                'sucursales_pickup' => $sucursalesPickup,
            ],
        ]);
    }
}