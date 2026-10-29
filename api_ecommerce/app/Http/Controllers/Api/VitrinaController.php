<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Discount\Discount;
use App\Models\Producto;
use App\Models\product\EspecificacionProducto;
use App\Models\Carrito\ConfiguracionCuota;

use App\Models\Configuracion\Sucursal;
use Illuminate\Support\Facades\DB;

use App\Models\Configuracion\ConfiguracionTienda;
use App\Models\PublicacionWeb;

use App\Models\Slider;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Models\Marca;
use App\Http\Resources\product\ProductoResource;
use App\Services\Catalogo\FichaTecnicaService;
use App\Services\Catalogo\BusquedaService;
use App\Services\Catalogo\ResultadoBusqueda;
use App\Services\Catalogo\BuscadorTiendaService;
use App\Models\Comportamiento\Busqueda;
use App\Services\Comportamiento\ActividadClienteService;

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
    // GET /api/tienda/home — público, consumido por el frontend del ecommerce (sin auth)
    public function home(Request $request): JsonResponse
    {
        $limite = min((int) $request->query('limite', 12), 24);

    // Descuentos activos, resueltos una sola vez por request y aplicados a
    // TODAS las secciones (no solo a en_oferta / oferta_del_dia), para que un
    // mismo producto muestre el mismo precio en cualquier lugar de la tienda.
    $descuentos = $this->descuentosActivosPorProducto();
 
    $nuevos = PublicacionWeb::publicadas()
        ->with(['producto.marca', 'producto.configuracionesCuota', 'producto.stock', 'categoria', 'imagenes'])
        ->orderByDesc('pub_creado')
        ->limit($limite)
        ->get()
        ->map(fn (PublicacionWeb $pub) => $this->formatearConOferta($pub, $descuentos));
 
    $destacados = PublicacionWeb::publicadas()
        ->destacadas()
        ->with(['producto.marca', 'producto.configuracionesCuota', 'producto.stock', 'categoria', 'imagenes'])
        ->limit($limite)
        ->get()
        ->map(fn (PublicacionWeb $pub) => $this->formatearConOferta($pub, $descuentos));
 
    $trending = PublicacionWeb::publicadas()
        ->trending()
        ->with(['producto.marca', 'producto.configuracionesCuota', 'producto.stock', 'categoria', 'imagenes'])
        ->limit($limite)
        ->get()
        ->map(fn (PublicacionWeb $pub) => $this->formatearConOferta($pub, $descuentos));
 
    $masVendidos = PublicacionWeb::publicadas()
        ->selling()
        ->with(['producto.marca', 'producto.configuracionesCuota', 'producto.stock', 'categoria', 'imagenes'])
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
            ->get()
            // Banner de una campaña que ya terminó (o aún no empieza): no se muestra
            ->reject(fn (Slider $s) => $s->sld_destino_tipo === 'campania' && !$s->campaniaVigente());

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

            return ProductoResource::collection($productos)->response();
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
            ->with(['producto.marca', 'producto.configuracionesCuota', 'producto.stock', 'categoria', 'imagenes'])
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


        // Antes: pub_prevta ?? producto->prd_precio. prd_precio ya no
        // existe (se renombro a prd_prevta y despues a prd_pvtmin), asi que
        // Eloquent devolvia null y el descuento se calculaba sobre 0.
        $precioBase = (float) $pub->producto->precioVitrina();

        return $descuentos
            ->map(fn (Discount $descuento) => [
                'descuento' => $descuento,
                'precio_final' => $this->calcularPrecioFinal($precioBase, $descuento),
            ])
            ->sortBy('precio_final')
            ->first();
    }
	
	 /**
     * Precio que ve el cliente: el de Gestión de Precios con la mejor oferta
     * vigente aplicada. Misma regla que formatearProducto(), sin armar toda
     * la tarjeta (cuotas, imágenes...), para poder usarse en cálculos masivos.
     */
    private function precioFinal(PublicacionWeb $pub, Collection $descuentosPorProducto): ?float
    {
        $oferta = $this->mejorOfertaPara($pub, $descuentosPorProducto);

        return $oferta
            ? (float) $oferta['precio_final']
            : $pub->producto?->precioVitrina();
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
    // Productos por lista de IDs (lo usa FavoritoController)
    // ---------------------------------------------------------------

    /**
     * Devuelve las tarjetas de producto (mismo formato que el Home: precio con
     * descuento, cuotas, stock...) para una lista de prd_id, CONSERVANDO el
     * orden recibido. Los productos que no existen o no están publicados se
     * omiten.
     *
     * @param  int[]  $ids
     * @return Collection<int, array>
     */
    public function productosPorIds(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        $descuentos = $this->descuentosActivosPorProducto();

        $publicaciones = PublicacionWeb::publicadas()
            ->whereIn('pub_prdid', $ids)
            ->with(['producto.marca', 'producto.configuracionesCuota', 'producto.stock', 'categoria', 'imagenes'])
            ->get()
            ->keyBy('pub_prdid');

        return collect($ids)
            ->map(fn ($id) => $publicaciones->get($id))
            ->filter()
            ->map(fn (PublicacionWeb $pub) => $this->formatearConOferta($pub, $descuentos))
            ->values();
    }

    // ---------------------------------------------------------------
    // Formato uniforme de salida (mismo shape en todas las secciones)
    // ---------------------------------------------------------------

    private function formatearProducto(PublicacionWeb $pub, ?array $mejorOferta = null): array
    {
        $producto = $pub->producto;
        // El precio sale del PRODUCTO (Gestion de Precios), no de la ficha
        // web: publicacion_web decide SI el producto aparece en la tienda y
        // COMO se ve, no a cuanto se vende.
        $precioOriginal = (float) $producto->precioVitrina();
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
            // Datos de venta al por mayor. El frontend los usa para la
            // leyenda "desde X unidades" y, en el detalle, para conmutar el
            // precio segun la cantidad elegida. La regla real vive en el
            // backend (Producto::precioPara()): el frontend solo muestra.
            'precio_mayorista' => $producto->prd_habmaj ? $producto->prd_pvtmay : null,
            'cantidad_minima_mayorista' => $producto->prd_habmaj ? $producto->umbralMayorista() : null,
            // true = no se vende por unidad suelta: la compra minima es el
            // umbral mayorista.
            'solo_mayorista' => $producto->soloMayorista(),
            'cantidad_minima' => $producto->cantidadMinimaVenta(),
            // Las cuotas se calculan sobre el precio YA con descuento: es lo
            // que el cliente va a financiar realmente.

            // La cuota se calcula sobre el importe que realmente entra al
            // carrito (precio con descuento x cantidad mínima de compra).
            // Para un producto normal la cantidad es 1; para uno solo
            // mayorista es su compra mínima.
            'cuotas' => $this->opcionesCuota(
                $producto,
                $precioFinal * $producto->cantidadMinimaVenta()
            ),

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
            // "Solo quedan X en existencia" también en las tarjetas del Home,
            // no solo en el Detalle. Usa el umbral de este producto si tiene
            // override en Publicación Web; si no, el general de la tienda.
            'poco_stock' => $this->pocoStock($producto, $pub),
        ];
    }

     private function pocoStock(Producto $producto, PublicacionWeb $pub): bool
    {
        if (! $producto->prd_afestk) {
            return false;
        }

        $config = ConfiguracionTienda::actual();
        $umbral = $pub->pub_pocstk_umbral ?? $config->ctn_pocstk_umbral;
        $unidades = (int) floor($this->stockDisponible($producto));

        return $config->ctn_pocstk_activo && $unidades > 0 && $unidades <= $umbral;
    }

    /**
     * Avisos de stock del Detalle del Producto.
     *
     *  - poco_stock: se evalúa acá (ver pocoStock()). El frontend solo lo
     *    muestra, no conoce el umbral.
     *  - mostrar_maximo: solo indica si el aviso "Máximo disponible" está
     *    habilitado (config general — no tiene override por producto). Si
     *    se alcanzó el máximo depende de la cantidad que el cliente elige
     *    en pantalla, así que eso lo resuelve el frontend.
     *
     * @return array{poco_stock: bool, unidades: int|null, mostrar_maximo: bool}
     */
         private function avisosStock(Producto $producto, PublicacionWeb $pub): array
    {
        $controlaStock = (bool) $producto->prd_afestk;

        if (! $controlaStock) {
            return [
                'poco_stock' => false,
                'unidades'   => null,
            ];
        }

        return [
            'poco_stock' => $this->pocoStock($producto, $pub),
            'unidades'   => (int) floor($this->stockDisponible($producto)),
        ];
    }

    /**
     * Suma de stock físico menos reservas, en todos los depósitos.
     * prd_stock ya no existe en producto (migración
     * adapt_producto_table_to_der): el inventario se deriva de la
     * relación Producto::stock() -> Compras\Stock.
     */
    /**
     * Opciones de cuotas configuradas para ESTE producto (configuracion_cuota),
     * activas y ordenadas. Reemplaza al viejo CuotaService/plan_cuota, que
     * era un plan único para toda la tienda.
     *
     * Se calculan sobre $precioContado (que ya viene con descuento aplicado,
     * si corresponde) para que el cliente financie lo que realmente va a
     * pagar, no el precio de lista.
     *
     * @return array<int, array{cantidad:int, recargo_pct:float, monto_cuota:float, total:float}>
     */
    private function opcionesCuota(Producto $producto, float $precioContado): array
    {
        if ($precioContado <= 0) {
            return [];
        }

        return $producto->configuracionesCuota
            ->where('cfc_estado', true)
            ->sortBy('cfc_cantidad')
            ->map(fn (ConfiguracionCuota $cfg) => [
                'cantidad'    => (int) $cfg->cfc_cantidad,
                'recargo_pct' => (float) $cfg->cfc_recargo_pct,
                'monto_cuota' => $cfg->montoCuota($precioContado),
                'total'       => $cfg->precioFinanciado($precioContado),
            ])
            ->values()
            ->all();
    }

     /**
     * Opciones de compra del detalle: un TRAMO de precio por modalidad
     * (minorista / mayorista), cada uno con sus cuotas calculadas sobre el
     * precio UNITARIO de ese tramo.
     *
     * Así el frontend solo elige el tramo según la cantidad y no recalcula
     * recargos: la financiación se calcula acá, con ConfiguracionCuota, la
     * misma lógica que usa el carrito. El orden es el pedido: primero el
     * precio que corresponde, después la financiación sobre ese precio.
     *
     * Nota: opcionesCuota() de la vitrina calcula sobre "precio x cantidad
     * mínima", que en un producto solo mayorista da la cuota del lote
     * completo. Acá las cuotas son POR UNIDAD, igual que car_montocuo.
     *
     * @return array{
     *   solo_mayorista: bool,
     *   cantidad_minima: int,
     *   minorista: array{precio_unitario: float, cuotas: array}|null,
     *   mayorista: array{precio_unitario: float, cantidad_minima: int, cuotas: array}|null
     * }
     */
    private function opcionesCompra(Producto $producto, array $formateado): array
    {
        $minorista = null;
 
        if ($producto->prd_habmin && $producto->prd_pvtmin !== null) {
            // precio_final ya trae el descuento vigente sobre el minorista.
            $precio = (float) $formateado['precio_final'];
 
            $minorista = [
                'precio_unitario' => $precio,
                'cuotas'          => $this->opcionesCuota($producto, $precio),
            ];
        }
 
        $mayorista = null;
 
        if ($producto->prd_habmaj && $producto->prd_pvtmay !== null) {
            // Producto solo mayorista: precio_final (con su descuento, si hay)
            // es el precio que se ve en el Home. Con ambas modalidades se usa
            // el precio mayorista de Gestión de Precios.
            $precio = $minorista === null
                ? (float) $formateado['precio_final']
                : (float) $producto->prd_pvtmay;
 
            $mayorista = [
                'precio_unitario' => $precio,
                // Cantidades enteras: q >= 10.5 equivale a q >= ceil(10.5).
                'cantidad_minima' => max(1, (int) ceil($producto->umbralMayorista())),
                'cuotas'          => $this->opcionesCuota($producto, $precio),
            ];
        }
 
        $soloMayorista = $minorista === null && $mayorista !== null;
 
        return [
            'solo_mayorista'  => $soloMayorista,
            'cantidad_minima' => $soloMayorista ? $mayorista['cantidad_minima'] : 1,
            'minorista'       => $minorista,
            'mayorista'       => $mayorista,
        ];
    }
 

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
            'enlace' => $slider->enlaceTienda(),
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
            ->with(['producto.marca', 'producto.configuracionesCuota', 'producto.stock', 'categoria', 'imagenes', 'vitrinas'])
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
        $a = (float) $actual->producto->precioVitrina();
        $b = (float) $candidata->producto->precioVitrina();
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

        // ---------------------------------------------------------------
    // Filtro avanzado / catálogo con búsqueda ("/productos-busqueda")
    // ---------------------------------------------------------------

    // GET /api/tienda/productos/filtros — público. Config para armar el
    // sidebar de filtros: categorías raíz (+ subárbol) con conteo, marcas
    // con conteo, y rango de precio actualmente publicado.
    public function configFiltros(): JsonResponse
    {
        $publicadasPorCategoria = PublicacionWeb::publicadas()
            ->whereNotNull('pub_catid')
            ->pluck('pub_catid')
            ->countBy();

        $categorias = Categoria::whereNull('cat_idpadr')
            ->orderBy('cat_nombre')
            ->get()
            ->map(function (Categoria $cat) use ($publicadasPorCategoria) {
                $idsSubarbol = $this->idsCategoriaYDescendientes($cat->cat_id);

                return [
                    'cat_id' => $cat->cat_id,
                    'cat_nombre' => $cat->cat_nombre,
                    'productos_count' => collect($idsSubarbol)
                        ->sum(fn ($id) => $publicadasPorCategoria->get($id, 0)),
                ];
            })
            ->values();

        $marcas = Marca::withCount(['productos as productos_count' => function ($q) {
                $q->whereHas('publicacionWeb', fn ($qq) => $qq->publicadas());
            }])
            ->where('mar_estado', 1)
            ->orderBy('mar_descri')
            ->get(['mar_id', 'mar_descri'])
            ->map(fn (Marca $m) => [
                'mar_id' => $m->mar_id,
                'mar_descri' => $m->mar_descri,
                'productos_count' => $m->productos_count,
            ]);

         // Rango del filtro de precio, con el mismo precio que ve el cliente.
        $descuentos = $this->descuentosActivosPorProducto();

        $precios = PublicacionWeb::publicadas()
            ->with('producto')
            ->get()
            ->map(fn (PublicacionWeb $pub) => $this->precioFinal($pub, $descuentos))
            ->filter(fn ($precio) => $precio !== null && $precio > 0);


        return response()->json([
            'data' => [
                'categorias' => $categorias,
                'marcas' => $marcas,
                'precio_min' => (float) floor($precios->min() ?? 0),
                'precio_max' => (float) ceil($precios->max() ?? 0),
            ],
        ]);
    }

     // GET /api/tienda/productos/buscar — público. Catálogo con filtros
    // (categoría, marca, precio, oferta, orden) + búsqueda por texto.
    public function buscar(Request $request, BuscadorTiendaService $buscador): JsonResponse
    {
        $categoriasSeleccionadas = (array) $request->query('categorias', []);
        $marcasSeleccionadas = (array) $request->query('marcas', []);
        $precioMin = $request->query('precio_min');
        $precioMax = $request->query('precio_max');
        $soloOferta = $request->boolean('en_oferta');
        $busqueda = trim((string) $request->query('busqueda', ''));
        $pagina = max(1, (int) $request->query('page', 1));
        $porPagina = min(48, max(1, (int) $request->query('por_pagina', 12)));

        // Con texto, lo natural es ordenar por relevancia; sin texto, por novedad.
        $orden = $request->query('orden', $busqueda !== '' ? 'relevancia' : 'nuevos');

        $idsCategorias = collect($categoriasSeleccionadas)
            ->flatMap(fn ($id) => $this->idsCategoriaYDescendientes((int) $id))
            ->unique()
            ->values()
            ->all();

        $descuentos = $this->descuentosActivosPorProducto();

        $query = PublicacionWeb::publicadas()
            ->with(['producto.marca', 'producto.stock', 'categoria', 'imagenes']);

        $resultado = null;
        if ($busqueda !== '') {
            // BM25 + trigramas y, si no alcanza, la interpretación del LLM.
            $resultado = $buscador->buscar($busqueda, $request->ip());
            $query->whereIn('pub_id', $resultado->ids);

            // "notebook de menos de 4 millones": el precio que la IA detectó
            // se usa solo si el cliente no movió el filtro de precio a mano.
            if (($precioMin === null || $precioMin === '') && $resultado->precioMin()) {
                $precioMin = $resultado->precioMin();
            }
            if (($precioMax === null || $precioMax === '') && $resultado->precioMax()) {
                $precioMax = $resultado->precioMax();
            }
        }


        if (!empty($idsCategorias)) {
            $query->whereIn('pub_catid', $idsCategorias);
        }

        if (!empty($marcasSeleccionadas)) {
            $query->whereHas('producto', fn ($q) => $q->whereIn('prd_marid', $marcasSeleccionadas));
        }

        if ($soloOferta) {
            $query->whereIn('pub_prdid', $descuentos->keys());
        }

        // ?campania=ID (desde un banner): solo los productos que esa campaña alcanza
        $campaniaId = (int) $request->query('campania', 0);
        if ($campaniaId) {
            $idsCampania = $descuentos
                ->filter(fn ($lista) => $lista->contains('id', $campaniaId))
                ->keys();
            $query->whereIn('pub_prdid', $idsCampania);
        }

        $productos = $query->orderByDesc('pub_creado')
            ->get()
            ->map(fn (PublicacionWeb $pub) => $this->formatearConOferta($pub, $descuentos));
		
		// Filtro de precio sobre el precio final (con oferta), el mismo que
        // muestra la tarjeta. Va después de formatear porque la oferta se
        // calcula en PHP, no en SQL.
        if ($precioMin !== null && $precioMin !== '') {
            $productos = $productos->filter(fn (array $p) => $p['precio_final'] >= (float) $precioMin);
        }

        if ($precioMax !== null && $precioMax !== '') {
            $productos = $productos->filter(fn (array $p) => $p['precio_final'] <= (float) $precioMax);
        }

        $productos = $productos->values();


        $productos = match ($orden) {
            'precio_asc' => $productos->sortBy('precio_final')->values(),
            'precio_desc' => $productos->sortByDesc('precio_final')->values(),
            'relevancia' => $this->ordenarPorRelevancia($productos, $resultado),
            default => $productos,
        };

        $total = $productos->count();
        $paginaProductos = $productos->slice(($pagina - 1) * $porPagina, $porPagina)->values();

        
        // Análisis de comportamiento: solo búsquedas con texto y en la página 1.
        $busquedaId = null;
        if ($resultado && $pagina === 1) {
            $busquedaId = app(ActividadClienteService::class)->registrarBusqueda(
                $request,
                $busqueda,
                (string) $resultado->modo,
                $total,
                $request->attributes->get('origen_busqueda', Busqueda::ORIGEN_TIENDA),
            );
        }
        return response()->json([
            'data' => $paginaProductos,
            'meta' => [
                'total' => $total,
                'page' => $pagina,
                'por_pagina' => $porPagina,
                'ultima_pagina' => (int) (ceil($total / $porPagina) ?: 1),
                // "Mostrando resultados para notebook" cuando se corrigió la consulta.
                'consulta_corregida' => $resultado?->consultaCorregida,
                'modo' => $resultado?->modo,
               // La tienda lo reenvía al abrir un producto (vista_producto.vpr_busid).
                'busqueda_id' => $busquedaId,
				// Lo que entendió la IA, para mostrarlo al cliente.
                'interpretacion' => $resultado?->interpretacion ? [
                    'explicacion' => $resultado->interpretacion->explicacion,
                    'filtros_relajados' => $resultado->filtrosRelajados,
                ] : null,

            ],
        ]);
    }
	
	
    /**
     * GET /api/tienda/productos/sugerencias?q=noteb — público.
     * Resultados instantáneos del buscador del header (mientras se escribe).
     * Devuelve los primeros productos y el total, para el enlace
     * "Ver todos los resultados (N)".
     */
    public function sugerencias(Request $request, BusquedaService $motor): JsonResponse
    {
        $consulta = trim((string) $request->query('q', ''));
        $limite = min(8, max(1, (int) $request->query('limite', 6)));

        if (mb_strlen($consulta) < 2) {
            return response()->json(['data' => [], 'meta' => ['total' => 0, 'consulta_corregida' => null]]);
        }

        $resultado = $motor->buscar($consulta);
        $idsVisibles = array_slice($resultado->ids, 0, $limite);
        $descuentos = $this->descuentosActivosPorProducto();

        $productos = PublicacionWeb::publicadas()
            ->whereIn('pub_id', $idsVisibles)
            ->with(['producto.marca', 'producto.stock', 'categoria', 'imagenes'])
            ->get()
            ->map(fn (PublicacionWeb $pub) => $this->formatearConOferta($pub, $descuentos));

        $productos = $this->ordenarPorRelevancia($productos, $resultado)
            ->map(fn (array $p) => [
                'pub_id' => $p['pub_id'],
                'titulo' => $p['titulo'],
                'slug' => $p['slug'],
                'categoria' => $p['categoria'],
                'imagen' => $p['imagen'],
                'precio_original' => $p['precio_original'],
                'precio_final' => $p['precio_final'],
                'descuento_pct' => $p['descuento_pct'],
            ]);

        return response()->json([
            'data' => $productos,
            'meta' => [
                'total' => $resultado->total(),
                'consulta_corregida' => $resultado->consultaCorregida,
            ],
        ]);
    }

    /** Ordena productos ya formateados según la posición que les dio el motor. */
    private function ordenarPorRelevancia($productos, ?ResultadoBusqueda $resultado)
    {
        if (! $resultado) {
            return $productos->values();
        }

        $posiciones = $resultado->posiciones();

        return $productos
            ->sortBy(fn (array $p) => $posiciones[$p['pub_id']] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * GET /api/tienda/productos/analizar-busqueda?q=laptop — público.
     * Responde en milisegundos si la búsqueda va a usar IA, para que la
     * tienda muestre el aviso de carga mientras corre la búsqueda real.
     */
    public function analizarBusqueda(Request $request, BuscadorTiendaService $buscador): JsonResponse
    {
        $consulta = trim((string) $request->query('q', ''));

        return response()->json([
            'usara_ia' => $consulta !== '' && $buscador->usaraIA($consulta),
        ]);
    }

    /** IDs de una categoría raíz + todas sus descendientes (árbol vía cat_idpadr). */
    private function idsCategoriaYDescendientes(int $catId): array
    {
        $ids = [$catId];
        $hijas = Categoria::where('cat_idpadr', $catId)->pluck('cat_id');

        foreach ($hijas as $hijaId) {
            $ids = array_merge($ids, $this->idsCategoriaYDescendientes((int) $hijaId));
        }

        return $ids;
    }

    // GET /api/tienda/categorias/menu — público. Árbol de categorías (raíz +
    // hijas) para el menú "Categorías" del header. Solo devuelve categorías
    // con al menos un producto publicado, para no linkear a una vacía.
    public function menuCategorias(): JsonResponse
    {
        $publicadasPorCategoria = PublicacionWeb::publicadas()
            ->whereNotNull('pub_catid')
            ->pluck('pub_catid')
            ->countBy();

        $todasCategorias = Categoria::orderBy('cat_nombre')->get(['cat_id', 'cat_nombre', 'cat_idpadr', 'cat_icono', 'cat_imagen']);
        $hijasPorPadre = $todasCategorias->whereNotNull('cat_idpadr')->groupBy('cat_idpadr');

        $tieneProductos = function (int $catId) use ($publicadasPorCategoria) {
            return collect($this->idsCategoriaYDescendientes($catId))
                ->sum(fn ($id) => $publicadasPorCategoria->get($id, 0)) > 0;
        };

        $menu = $todasCategorias->whereNull('cat_idpadr')
            ->filter(fn (Categoria $cat) => $tieneProductos($cat->cat_id))
            ->map(function (Categoria $cat) use ($hijasPorPadre, $tieneProductos) {
                return [
                    'cat_id' => $cat->cat_id,
                    'cat_nombre' => $cat->cat_nombre,
                    'cat_icono' => $cat->cat_icono,
                    'cat_imagen' => $cat->cat_imagen? rtrim(config('app.url'), '/') . '/storage/' . $cat->cat_imagen: null,
                    'hijas' => ($hijasPorPadre->get($cat->cat_id) ?? collect())
                        ->filter(fn (Categoria $hija) => $tieneProductos($hija->cat_id))
                        ->map(fn (Categoria $hija) => [
                            'cat_id' => $hija->cat_id,
                            'cat_nombre' => $hija->cat_nombre,
                        ])
                        ->values(),
                ];
            })
            ->values();

        return response()->json(['data' => $menu]);
    }
	
	// GET /api/tienda/productos/comparar?slugs=a,b,c — público
    // Comparador: devuelve en UNA petición los datos de hasta 4 productos
    // (precio/oferta/stock/cuotas con el mismo formato que el detalle, más su
    // ficha técnica). El orden de la respuesta es el de los slugs recibidos.
    public function comparar(Request $request): JsonResponse
    {
        $slugs = collect(explode(',', (string) $request->query('slugs', '')))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->unique()
            ->take(4)
            ->values();

        if ($slugs->isEmpty()) {
            return response()->json(['data' => ['productos' => []]]);
        }

        $publicaciones = PublicacionWeb::publicadas()
            ->whereIn('pub_slug', $slugs->all())
            ->with(['producto.marca', 'producto.configuracionesCuota', 'producto.stock.deposito.sucursal', 'producto.padre.publicacionWeb', 'categoria', 'imagenes'])
            ->get()
            ->keyBy('pub_slug');

        $descuentos = $this->descuentosActivosPorProducto();
        $fichaTecnica = app(FichaTecnicaService::class);

        // Un slug que ya no está publicado simplemente no aparece: el frontend
        // lo descarta de la lista del cliente.
        $productos = $slugs
            ->filter(fn ($slug) => $publicaciones->has($slug))
            ->map(function ($slug) use ($publicaciones, $descuentos, $fichaTecnica) {
                $pub = $publicaciones->get($slug);

                return [
                    'producto' => $this->formatearConOferta($pub, $descuentos),
                    'especificaciones' => $fichaTecnica->fichaParaMostrar($pub),
                ];
            })
            ->values();

        return response()->json(['data' => ['productos' => $productos]]);
    }

   // GET /api/tienda/producto/{slug} — público
    public function detalle(string $slug): JsonResponse
    {
        $pub = PublicacionWeb::publicadas()
            ->where('pub_slug', $slug)
            ->with(['producto.marca', 'producto.configuracionesCuota', 'producto.stock.deposito.sucursal', 'producto.padre.publicacionWeb', 'categoria', 'imagenes'])
            ->firstOrFail();

        $producto = $pub->producto;

        // Mismo mecanismo de descuentos que el home: sin esto el detalle
        // mostraba siempre el precio de lista aunque la campaña estuviera activa.
        $descuentos = $this->descuentosActivosPorProducto();

           $especificaciones = app(FichaTecnicaService::class)->fichaParaMostrar($pub);


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

             
        $productoFormateado = $this->formatearConOferta($pub, $descuentos);
 
        return response()->json([
            'data' => [
                'producto' => $productoFormateado,
                'compra' => $this->opcionesCompra($producto, $productoFormateado),
                'stock_avisos' => $this->avisosStock($producto, $pub),
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

 // GET /api/tienda/producto/{slug}/stock-sucursales — público
    public function stockSucursales(string $slug): JsonResponse
    {
        $pub = PublicacionWeb::publicadas()
            ->where('pub_slug', $slug)
            ->with('producto')
            ->firstOrFail();

        $producto = $pub->producto;

        // prd_afestk = false significa "no controla stock", no "sin stock".
        $controlaStock = (bool) $producto->prd_afestk;

        $disponiblePorSucursal = collect();

        if ($controlaStock) {
            $disponiblePorSucursal = DB::table('stock')
                ->join('deposito', 'sto_depid', '=', 'dpo_id')
                ->where('sto_prdid', $producto->prd_id)
                ->where('dpo_estado', 1)
                ->groupBy('dpo_sucid')
                ->select('dpo_sucid', DB::raw('SUM(sto_cantid - sto_reserv) AS disponible'))
                ->pluck('disponible', 'dpo_sucid');
        }

        $sucursales = Sucursal::query()
            ->visibleEnStockWeb()
            ->with('ciudad')
            ->orderByDesc('suc_princi')
            ->orderBy('suc_nombre')
            ->get()
            ->map(function (Sucursal $s) use ($disponiblePorSucursal, $controlaStock) {
                $disponible = (float) ($disponiblePorSucursal[$s->suc_id] ?? 0);

                return [
                    'suc_id'     => $s->suc_id,
                    'nombre'     => $s->suc_nombre,
                    'direccion'  => $s->suc_direccion,
                    'horario'    => $s->suc_horario,
                    'telefono'   => $s->suc_telef ?: $s->suc_cel,
                    'ciudad'     => $s->ciudad?->ciu_descri,
                    'principal'  => (int) $s->suc_princi === 1,
                    'retiro'     => (bool) $s->suc_retiro,
                    'delivery'   => (bool) $s->suc_delivery,
                    'existencia' => $controlaStock ? (int) max(0, floor($disponible)) : null,
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'producto' => [
                    'titulo'         => $pub->pub_titulo,
                    'controla_stock' => $controlaStock,
                ],
                'sucursales' => $sucursales,
            ],
        ], 200);
    }

}