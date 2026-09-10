<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discount\Discount;
use App\Models\Producto;
use App\Models\PublicacionWeb;
use App\Models\Slider;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Models\Product\EspecificacionProducto;

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
 
    $nuevos = PublicacionWeb::publicadas()
        ->with(['producto.marca', 'categoria', 'imagenes'])
        ->orderByDesc('pub_creado')
        ->limit($limite)
        ->get()
        ->map(fn (PublicacionWeb $pub) => $this->formatearProducto($pub));
 
    $destacados = PublicacionWeb::publicadas()
        ->destacadas()
        ->with(['producto.marca', 'categoria', 'imagenes'])
        ->limit($limite)
        ->get()
        ->map(fn (PublicacionWeb $pub) => $this->formatearProducto($pub));
 
    $trending = PublicacionWeb::publicadas()
        ->trending()
        ->with(['producto.marca', 'categoria', 'imagenes'])
        ->limit($limite)
        ->get()
        ->map(fn (PublicacionWeb $pub) => $this->formatearProducto($pub));
 
    $masVendidos = PublicacionWeb::publicadas()
        ->selling()
        ->with(['producto.marca', 'categoria', 'imagenes'])
        ->limit($limite)
        ->get()
        ->map(fn (PublicacionWeb $pub) => $this->formatearProducto($pub));
 
    [$enOferta, $ofertaDelDia] = $this->resolverOfertas($limite);
 
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

    // ---------------------------------------------------------------
    // Resolución de ofertas activas (reutiliza el módulo de Discounts existente)
    // ---------------------------------------------------------------

    /**
     * @return array{0: Collection, 1: Collection} [en_oferta, oferta_del_dia]
     */
    private function resolverOfertas(int $limite): array
    {
        $descuentosPorProducto = $this->descuentosActivosPorProducto();

        if ($descuentosPorProducto->isEmpty()) {
            return [collect(), collect()];
        }

        $formateados = PublicacionWeb::publicadas()
            ->whereIn('pub_prdid', $descuentosPorProducto->keys())
            ->with(['producto.marca', 'categoria', 'imagenes'])
            ->get()
            ->map(function (PublicacionWeb $pub) use ($descuentosPorProducto) {
                $precioBase = (float) ($pub->pub_prevta ?? $pub->producto->prd_precio);

                // Si varios descuentos activos aplican al mismo producto
                // (ej. uno por categoría y otro puntual), se usa el que le
                // conviene más al cliente (precio final más bajo) — así se
                // evita mostrar un precio distinto según qué regla "gane".
                $mejor = $descuentosPorProducto->get($pub->pub_prdid)
                    ->map(fn (Discount $descuento) => [
                        'descuento' => $descuento,
                        'precio_final' => $this->calcularPrecioFinal($precioBase, $descuento),
                    ])
                    ->sortBy('precio_final')
                    ->first();

                return $this->formatearProducto($pub, $mejor);
            });

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
        $precioOriginal = (float) ($pub->pub_prevta ?? $producto->prd_precio);
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
            'descuento_pct' => $descuentoPct,
            'oferta_fin' => $ofertaFin,
            'es_flash' => $esFlash,
            // Todavía no existe un módulo de reseñas: se manda null a
            // propósito para que el frontend NO invente estrellas ni
            // cantidad de reseñas falsas.
            'rating' => null,
        ];
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

    // GET /api/tienda/producto/{slug} — público
public function detalle(string $slug): JsonResponse
{
    $pub = PublicacionWeb::publicadas()
        ->where('pub_slug', $slug)
        ->with(['producto.marca', 'producto.padre.publicacionWeb', 'categoria', 'imagenes'])
        ->firstOrFail();

    $producto = $pub->producto;

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

    return response()->json([
        'data' => [
            'producto' => $this->formatearProducto($pub),
            'especificaciones' => $especificaciones,
            'colores_disponibles' => $coloresDisponibles,
        ],
    ]);
}
}
