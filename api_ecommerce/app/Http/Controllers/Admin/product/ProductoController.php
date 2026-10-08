<?php

namespace App\Http\Controllers\Admin\product;

use App\Http\Requests\product\ProductoRequest;
use App\Http\Resources\product\ProductoResource;
use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\PublicacionWeb;
use App\Models\Product\Atributo;
use App\Services\Catalogo\FichaTecnicaService;
use App\Models\Compras\ProductoEquivalencia;
use App\Services\Precio\PriceService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductoController extends Controller
{
    private const DEPENDENCIAS = [
        'compra_detalle' => ['columna' => 'cmd_prdid', 'etiqueta' => 'compra(s) registrada(s)'],
        'stock'          => ['columna' => 'sto_prdid', 'etiqueta' => 'registro(s) de stock'],
    ];

    public function __construct(
        private readonly PriceService $priceService,
        private readonly FichaTecnicaService $fichaTecnica,
    ) {
    }

    private function contarUsos(): array
    {
        $usos = [];

        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }

            $conteos = DB::table($tabla)
                ->select($info['columna'], DB::raw('COUNT(*) AS total'))
                ->groupBy($info['columna'])
                ->pluck('total', $info['columna']);

            foreach ($conteos as $prdId => $total) {
                $usos[$prdId][$tabla] = (int) $total;
            }
        }

        return $usos;
    }

    /**
     * Adjunta stock_actual (suma en todos los depositos) y precio_sugerido
     * (costo promedio ponderado GLOBAL x margen minorista EFECTIVO) a una
     * coleccion o modelo de Producto. Se hace con UNA sola consulta
     * agregada por lote, no una consulta por producto, para no generar
     * N+1 en el listado.
     *
     * Si la tabla 'stock' todavia no existe (antes de correr las
     * migraciones de Compra), deja ambos campos en null -- el Resource los
     * omite automaticamente via whenNotNull().
     */
    private function conStock(Collection|Producto $productos): Collection|Producto
    {
        if (!Schema::hasTable('stock')) {
            return $productos;
        }

        $coleccion = $productos instanceof Producto ? collect([$productos]) : $productos;
        $ids = $coleccion->pluck('prd_id');

        $agregados = DB::table('stock')
            ->select(
                'sto_prdid',
                DB::raw('SUM(sto_cantid) AS cantidad_total'),
                DB::raw('SUM(sto_cantid * sto_cosprm) AS costo_total')
            )
            ->whereIn('sto_prdid', $ids)
            ->groupBy('sto_prdid')
            ->get()
            ->keyBy('sto_prdid');

        foreach ($coleccion as $producto) {
            $fila = $agregados->get($producto->prd_id);
            $cantidadTotal = $fila ? (float) $fila->cantidad_total : 0;

            $producto->stock_actual = $cantidadTotal;

            // Antes se pedia prd_margen !== null (margen INDIVIDUAL
            // obligatorio). Ahora siempre hay margen -- el general como
            // piso -- asi que precio_sugerido solo depende de si hay stock.
            if ($fila && $cantidadTotal > 0) {
                $costoPromedioGlobal = ((float) $fila->costo_total) / $cantidadTotal;
                $margenEfectivo = $this->priceService->margenEfectivo($producto, 'minorista');
                $producto->precio_sugerido = round($costoPromedioGlobal * (1 + $margenEfectivo / 100), 2);
            } else {
                $producto->precio_sugerido = null;
            }
        }

        return $productos;
    }

    /**
     * Crea, dentro de la MISMA transacción del padre, una fila de producto
     * por cada elemento de $filas (alta en lote, Opción 3). Cada variante
     * hereda marca/IVA/unidad/afecta-stock/estado del padre, incluyendo su
     * override individual de margen si lo tiene (prd_marmin/prd_marmaj/
     * prd_canminmaj -- null en el padre = usa el general de
     * configuracion_precio, y eso también se hereda tal cual).
     * prd_descri/prd_codinv/prd_stkmin/atb_id/prp_id vienen de la fila.
     *
     * Toda variante nace, además, con su Publicación Web en borrador
     * (pub_estado=0, sin categoría todavía -- eso se completa cuando el
     * admin entra a esa pantalla) y su especificación ya cargada con el
     * Atributo/Propiedad de esa fila. No es opcional: si es una variante,
     * por definición se diferencia por algo, y eso es justamente lo que
     * queda registrado acá.
     */
    private function crearVariantes(Producto $padre, array $filas): void
    {
        foreach ($filas as $fila) {
            $variante = Producto::create([
                'prd_padre_id'  => $padre->prd_id,
                'prd_marid'     => $padre->prd_marid,
                'prd_tivid'     => $padre->prd_tivid,
                'prd_umdid'     => $padre->prd_umdid,
                'prd_afestk'    => $padre->prd_afestk,
                'prd_estado'    => $padre->prd_estado,
                'prd_marmin'    => $padre->prd_marmin,
                'prd_marmaj'    => $padre->prd_marmaj,
                'prd_canminmaj' => $padre->prd_canminmaj,
                'prd_codinv'    => $fila['prd_codinv'],
                'prd_descri'    => $fila['prd_descri'],
                'prd_stkmin'    => $fila['prd_stkmin'],
            ]);

            $publicacion = PublicacionWeb::create([
                'pub_prdid'  => $variante->prd_id,
                'pub_titulo' => $variante->prd_descri,
                // pub_catid: se completa después, en Publicación Web.
                // pub_slug: se autogenera solo desde pub_titulo (ver
                // PublicacionWeb::boot()). pub_estado: default 0 (borrador).
            ]);

            // El atributo diferenciador puede ser de cualquier tipo
            // (Texto, Número, Seleccionable o Múltiple). Se delega en la
            // Ficha Técnica para que se guarde exactamente igual que si
            // se cargara desde esa pantalla (ya fue validado en ProductoRequest).
            $atributo = Atributo::with('propiedades')->findOrFail($fila['atb_id']);
            $this->fichaTecnica->guardarDiferenciador(
                $publicacion,
                $atributo,
                $this->fichaTecnica->valorDesdeFila($fila)
            );
        }
    }

    /**
     * Sincronización automática (sin casilla, siempre): si el padre tiene
     * variantes propias, sus marca/IVA/unidad Y su override de margen se
     * propagan a todas -- así nunca quedan desalineadas de lo que el admin
     * definió como compartido, tampoco en precio. prd_estado/prd_afestk/
     * prd_stkmin NO se propagan: son independientes por diseño (cada
     * variante puede tener su propio stock mínimo o estar desactivada sin
     * afectar a sus hermanas). prd_pvtmin/prd_pvtmay (el precio ya
     * CALCULADO) tampoco se tocan acá: eso lo recalcula PriceService según
     * el costo propio de cada variante, no se copia del padre.
     */
    private function sincronizarVariantes(Producto $padre): void
    {
        Producto::where('prd_padre_id', $padre->prd_id)->update([
            'prd_marid'     => $padre->prd_marid,
            'prd_tivid'     => $padre->prd_tivid,
            'prd_umdid'     => $padre->prd_umdid,
            'prd_marmin'    => $padre->prd_marmin,
            'prd_marmaj'    => $padre->prd_marmaj,
            'prd_canminmaj' => $padre->prd_canminmaj,
        ]);
    }

    // GET /api/admin/productos?search=&marca=&estado=&categoria=&estado_publicacion=&page=&per_page=
    public function index(Request $request): JsonResponse
    {
        // Colapsado por defecto: solo modelos base / productos sueltos
        // (las variantes se piden aparte, GET /productos/{id}, al expandir
        // una fila). Publicación Web necesita ver TODOS los productos por
        // igual -- cada variante publica por separado -- así que manda
        // ?incluir_variantes=1 para pedir la lista sin colapsar.
        $incluirVariantes = $request->boolean('incluir_variantes');

       $query = Producto::query();

		if ($request->filled('padre')) {
		// Filas hijas al expandir un padre en Publicación Web: mismo Resource
		// que el listado, así traen título web, categoría, imagen y estado.
			$query->where('prd_padre_id', $request->integer('padre'));
		} elseif (!$incluirVariantes) {
			$query->whereNull('prd_padre_id')->withCount('variantes');
		}
        $query->with([
            'marca',
            'tipoIva',
            'unidadMedida',
            'publicacionWeb.categoria',
            'publicacionWeb.imagenes',
        ]);

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(prd_descri) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(prd_codinv) LIKE ?', [$search])
                  ->orWhereHas('publicacionWeb', fn ($p) => $p->whereRaw('LOWER(pub_titulo) LIKE ?', [$search]));
            });
        }

        if ($request->filled('marca')) {
            $query->where('prd_marid', $request->marca);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('prd_estado', $request->estado);
        }

        // Filtro de CATEGORÍA: vive en publicacion_web.pub_catid, no en producto.
        if ($request->filled('categoria')) {
            $categoria = $request->categoria;
            $query->whereHas('publicacionWeb', fn ($p) => $p->where('pub_catid', $categoria));
        }

        // Filtro de ESTADO DE PUBLICACIÓN (distinto de prd_estado, que es inventario).
        if ($request->filled('estado_publicacion')) {
            switch ($request->estado_publicacion) {
                case 'sin_publicar':
                    $query->doesntHave('publicacionWeb');
                    break;
                case 'borrador':
                    $query->whereHas('publicacionWeb', fn ($p) => $p->where('pub_estado', 0));
                    break;
                case 'publicado':
                    $query->whereHas('publicacionWeb', fn ($p) => $p->where('pub_estado', 1));
                    break;
                case 'archivado':
                    $query->whereHas('publicacionWeb', fn ($p) => $p->where('pub_estado', 2));
                    break;
            }
        }

        $perPage = min(max((int) $request->input('per_page', 10), 1), 100);
        $productos = $query->orderBy('prd_descri')->paginate($perPage);

        $usos = $this->contarUsos();

        $productos->getCollection()->each(function ($p) use ($usos) {
            $p->usos_count = array_sum($usos[$p->prd_id] ?? []);
        });

        $productos->setCollection($this->conStock($productos->getCollection()));

        return ProductoResource::collection($productos)->response();
    }

    // GET /api/admin/productos/config
    public function config(): JsonResponse
    {
        $marcas = DB::table('marca')
            ->where('mar_estado', 1)
            ->orderBy('mar_descri')
            ->get(['mar_id', 'mar_descri']);

        $tiposIva = DB::table('tipo_iva')
            ->where('tiv_estado', 1)
            ->orderBy('tiv_tasa')
            ->get(['tiv_id', 'tiv_descri', 'tiv_tasa']);

        $unidades = DB::table('unidad_medida')
            ->where('umd_estado', 1)
            ->orderBy('umd_nombre')
            ->get(['umd_id', 'umd_codigo', 'umd_nombre', 'umd_decimal']);

        // Atributos para el "Se diferencia por" de las variantes: TODOS los
        // tipos (Texto, Número, Seleccionable, Múltiple), activos y sin
        // paginar (antes se tomaban los 25 primeros de /admin/atributos).
        $atributosVariante = Atributo::with(['propiedades:prp_id,prp_atbid,prp_nombre'])
            ->where('atb_estado', 1)
            ->orderBy('atb_nombre')
            ->get(['atb_id', 'atb_nombre', 'atb_tipo', 'atb_unidad']);

        return response()->json([
            'marcas'             => $marcas,
            'tipos_iva'          => $tiposIva,
            'unidades'           => $unidades,
            'atributos_variante' => $atributosVariante,
        ], 200);
    }

    // GET /api/admin/productos/select
    public function select(): JsonResponse
    {
        $productos = Producto::with(['tipoIva', 'unidadMedida'])
            ->where('prd_estado', 1)
            ->orderBy('prd_descri')
            ->get(['prd_id', 'prd_codinv', 'prd_descri', 'prd_pvtmin', 'prd_pvtmay', 'prd_tivid', 'prd_umdid']);

        $productos->each(function ($p) {
            $p->tiv_tasa = $p->tipoIva?->tiv_tasa;
            $p->umd_codigo = $p->unidadMedida?->umd_codigo;
        });

        return response()->json(['data' => $productos], 200);
    }

    // GET /api/admin/productos/{id}
    public function show(int $id): JsonResponse
    {
        $producto = Producto::with([
            'marca', 'tipoIva', 'unidadMedida', 'padre',
            'variantes.publicacionWeb', 'variantes.marca', 'variantes.unidadMedida',
        ])->find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $producto = $this->conStock($producto);

        // El listado colapsado (index()) necesita poder pintar la fila de
        // cada variante igual de completa que la del padre (marca, unidad,
        // stock, si está en uso para habilitar/deshabilitar "Eliminar") al
        // expandir -- así que se les calcula acá también, no solo al padre.
        if ($producto->variantes->isNotEmpty()) {
            $this->conStock($producto->variantes);
        }

        $usos = $this->contarUsos();
        $producto->usos_count = array_sum($usos[$producto->prd_id] ?? []);
        $producto->variantes->each(function ($v) use ($usos) {
            $v->usos_count = array_sum($usos[$v->prd_id] ?? []);
        });

        return response()->json(['data' => new ProductoResource($producto)], 200);
    }

    // POST /api/admin/productos
    public function store(ProductoRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['prd_estado'] = $datos['prd_estado'] ?? 1;
        $datos['prd_afestk'] = $datos['prd_afestk'] ?? 1;

        // No son columnas de "producto" -- se usan aparte para la
        // equivalencia inicial y no deben llegar a Producto::create()
        // (Eloquent los ignoraria igual por no estar en $fillable, pero
        // se sacan explicitamente para que $datos refleje solo el producto).
        $tieneEquivCompra = (bool) ($datos['tiene_equiv_compra'] ?? false);
        $peqUmdid  = $datos['peq_umdid']  ?? null;
        $peqFactor = $datos['peq_factor'] ?? null;
        $variantes = $datos['variantes'] ?? [];
        unset($datos['tiene_equiv_compra'], $datos['peq_umdid'], $datos['peq_factor'], $datos['variantes']);

        $producto = DB::transaction(function () use ($datos, $tieneEquivCompra, $peqUmdid, $peqFactor, $variantes) {
            $producto = Producto::create($datos);

            if ($tieneEquivCompra) {
                ProductoEquivalencia::create([
                    'peq_prdid'  => $producto->prd_id,
                    'peq_umdid'  => $peqUmdid,
                    'peq_factor' => $peqFactor,
                    'peq_estado' => 1,
                ]);
            }

            if (!empty($variantes)) {
                $this->crearVariantes($producto, $variantes);
            }

            return $producto;
        });

        $producto->load(['marca', 'tipoIva', 'unidadMedida', 'variantes']);
        $producto = $this->conStock($producto);

        return response()->json([
            'message' => 'Producto registrado correctamente',
            'data'    => new ProductoResource($producto),
        ], 201);
    }

    // PUT /api/admin/productos/{id}
    public function update(ProductoRequest $request, int $id): JsonResponse
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        // request->validated() nunca trae stock_actual/precio_sugerido: no
        // estan en las reglas del Request, se ignoran aunque lleguen.
        //
        // prd_padre_id se saca EXPLICITAMENTE: el form de edición ya no
        // tiene forma de mandarlo (se gestiona solo, vía crearVariantes()/
        // sincronizarVariantes()) -- si Laravel llegara a devolverlo en
        // null por estar ausente del payload, un update() sin este unset
        // desvincularía silenciosamente una variante existente de su padre.
        $datos = $request->validated();
        $variantes = $datos['variantes'] ?? [];
        unset(
            $datos['variantes'], $datos['tiene_equiv_compra'], $datos['peq_umdid'], $datos['peq_factor'],
            $datos['prd_padre_id']
        );

        DB::transaction(function () use ($producto, $datos, $variantes) {
            $producto->update($datos);

            // Sync automático: si YA tenía variantes, sus marca/IVA/unidad
            // se actualizan para no quedar desalineadas del padre.
            if ($producto->variantes()->exists()) {
                $this->sincronizarVariantes($producto);
            }

            // Filas nuevas cargadas ahora desde el mismo formulario de
            // edición (Opción 3 también disponible al editar, no solo al crear).
            if (!empty($variantes)) {
                $this->crearVariantes($producto, $variantes);
            }
        });

        $producto->load(['marca', 'tipoIva', 'unidadMedida', 'variantes']);
        $producto = $this->conStock($producto);

        return response()->json([
            'message' => 'Producto actualizado correctamente',
            'data'    => new ProductoResource($producto),
        ], 200);
    }

    // DELETE /api/admin/productos/{id}
    public function destroy(int $id): JsonResponse
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $detalle = [];

        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }

            $total = DB::table($tabla)->where($info['columna'], $id)->count();

            if ($total > 0) {
                $detalle[] = "{$total} {$info['etiqueta']}";
            }
        }

        if (!empty($detalle)) {
            return response()->json([
                'message' => 'No se puede eliminar: el producto tiene ' . implode(', ', $detalle)
                             . '. Desactívelo en lugar de eliminarlo.',
            ], 409);
        }

        $producto->delete();

        return response()->json(['message' => 'Producto eliminado correctamente'], 200);
    }

    // PATCH /api/admin/productos/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $producto->prd_estado = $producto->prd_estado == 1 ? 0 : 1;
        $producto->save();
        $producto->load(['marca', 'tipoIva', 'unidadMedida']);
        $producto = $this->conStock($producto);

        return response()->json([
            'message' => $producto->prd_estado == 1
                ? 'Producto activado correctamente'
                : 'Producto desactivado correctamente',
            'data'    => new ProductoResource($producto),
        ], 200);
    }
}