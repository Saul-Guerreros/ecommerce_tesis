<?php

namespace App\Services\Catalogo;

use App\Services\IA\GeminiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Capa LLM del buscador: convierte una consulta en lenguaje natural en
 * FILTROS ESTRUCTURADOS (JSON) sobre el catálogo real.
 *
 * Principios:
 *  1. El LLM solo puede elegir entre ids que existen: se le pasa el
 *     vocabulario de la tienda (subcategorías, marcas, atributos y sus
 *     opciones) y su respuesta se valida contra ese mismo vocabulario.
 *     Lo que no exista se descarta: el modelo no puede "inventar" filtros.
 *  2. Es una capa de RESCATE: solo se llama cuando BM25 no alcanza (ver
 *     BuscadorTiendaService). Las búsquedas simples nunca usan IA.
 *  3. Nunca rompe la búsqueda: sin clave, sin conexión, timeout o límite
 *     de uso alcanzado -> devuelve null y se usan los resultados de BM25.
 *  4. Costo y velocidad: cada interpretación se guarda en caché (la misma
 *     consulta no se le pregunta dos veces al modelo) y hay un límite de
 *     llamadas por IP.
 */
class InterpreteConsultaService
{
    /** Horas que se reutiliza la interpretación de una misma consulta. */
    private const HORAS_CACHE = 24;
    /** Minutos que se reutiliza el vocabulario de la tienda. */
    private const MINUTOS_VOCABULARIO = 10;
    /** Llamadas al LLM por minuto y por IP (protege el costo de la API). */
    private const LLAMADAS_POR_MINUTO = 20;

    public function __construct(private GeminiClient $gemini)
    {
    }

    /** Clave de caché de una consulta (v2: incluye marcas excluidas). */
    public static function claveCache(string $consulta): string
    {
        return 'busqueda:ia:v2:' . md5(Str::of($consulta)->ascii()->lower()->squish()->value());
    }
    
    /** ¿Hay un LLM configurado? */
    public function disponible(): bool
    {
        return $this->gemini->disponible();
    }

    /** ¿Esta consulta ya fue interpretada antes? (responde al instante, sin llamar al LLM) */
    public function estaEnCache(string $consulta): bool
    {
        return Cache::has(self::claveCache($consulta));
    }
    

    public function interpretar(string $consulta, ?string $ip = null): ?InterpretacionConsulta
    {
        if (! $this->gemini->disponible()) {
            return null;
        }

        $clave = self::claveCache($consulta);

        if ($guardada = Cache::get($clave)) {
            return InterpretacionConsulta::fromArray($guardada);
        }

        $limite = 'busqueda-ia:' . ($ip ?? 'global');
        if (RateLimiter::tooManyAttempts($limite, self::LLAMADAS_POR_MINUTO)) {
            return null;
        }
        RateLimiter::hit($limite, 60);

        $vocabulario = $this->vocabulario();
        $respuesta = $this->gemini->generarJson(
            $this->instrucciones(),
            $this->mensaje($consulta, $vocabulario),
            $this->esquema()
        );

        if (! $respuesta) {
            return null; // no se cachea un fallo: la próxima vez se reintenta
        }

        $interpretacion = $this->sanear($respuesta, $vocabulario);
        Cache::put($clave, $interpretacion->toArray(), now()->addHours(self::HORAS_CACHE));

        return $interpretacion;
    }

    // =====================================================================
    // Vocabulario de la tienda (lo único que el LLM puede usar)
    // =====================================================================

    public function vocabulario(): array
    {
        return Cache::remember('busqueda:ia:vocabulario', now()->addMinutes(self::MINUTOS_VOCABULARIO), function () {
            // Solo subcategorías y marcas que tienen productos publicados.
            $subcategorias = DB::table('indice_busqueda as i')
                ->join('categoria as c', 'c.cat_id', '=', 'i.ibu_catid')
                ->leftJoin('categoria as p', 'p.cat_id', '=', 'c.cat_idpadr')
                ->select('c.cat_id as id', 'c.cat_nombre as nombre', 'p.cat_nombre as grupo')
                ->distinct()
                ->orderBy('c.cat_nombre')
                ->get();

            $marcas = DB::table('indice_busqueda as i')
                ->join('marca as m', 'm.mar_id', '=', 'i.ibu_marid')
                ->select('m.mar_id as id', 'm.mar_descri as nombre')
                ->distinct()
                ->orderBy('m.mar_descri')
                ->get();

            // Atributos filtrables: numéricos y seleccionables que figuran en
            // alguna plantilla (los de texto libre se resuelven por palabras).
            $atributos = DB::table('categoria_atributo as ca')
                ->join('atributo as a', 'a.atb_id', '=', 'ca.cta_atbid')
                ->join('categoria as c', 'c.cat_id', '=', 'ca.cta_catid')
                ->whereIn('a.atb_tipo', [2, 3, 4])
                ->whereNull('a.atb_eliminado')
                ->select('a.atb_id', 'a.atb_nombre', 'a.atb_tipo', 'a.atb_unidad', 'c.cat_nombre')
                ->get()
                ->groupBy('atb_id')
                ->map(function ($filas) {
                    $a = $filas->first();
                    $dato = [
                        'id'            => (int) $a->atb_id,
                        'nombre'        => $a->atb_nombre,
                        'tipo'          => (int) $a->atb_tipo === 2 ? 'numero' : 'opciones',
                        'subcategorias' => $filas->pluck('cat_nombre')->unique()->values()->all(),
                    ];
                    if ((int) $a->atb_tipo === 2) {
                        $dato['unidad'] = $a->atb_unidad;
                    } else {
                        $dato['opciones'] = DB::table('propiedad')
                            ->where('prp_atbid', $a->atb_id)
                            ->whereNull('prp_eliminado')
                            ->orderBy('prp_nombre')
                            ->get(['prp_id as id', 'prp_nombre as nombre'])
                            ->map(fn ($p) => ['id' => (int) $p->id, 'nombre' => $p->nombre])
                            ->all();
                    }
                    return $dato;
                })
                ->values();

            return [
                'subcategorias' => $subcategorias->map(fn ($s) => ['id' => (int) $s->id, 'nombre' => $s->nombre, 'grupo' => $s->grupo])->all(),
                'marcas'        => $marcas->map(fn ($m) => ['id' => (int) $m->id, 'nombre' => $m->nombre])->all(),
                'atributos'     => $atributos->all(),
            ];
        });
    }

    // =====================================================================
    // Prompt y esquema
    // =====================================================================

    private function instrucciones(): string
    {
        return <<<'TXT'
Sos el intérprete de búsquedas de Z Informática, una tienda de informática de Paraguay.
Convertís lo que escribe un cliente en filtros sobre el catálogo que se te da.

Reglas:
1. Usá SOLO ids que aparezcan en el catálogo. Si nada corresponde, dejá la lista vacía o null. Nunca inventes ids.
2. consulta_reescrita: palabras clave en español, como aparecerían en el título o la ficha de los productos buscados.
   - Corregí errores de tipeo.
   - Traducí sinónimos al término de la tienda: laptop/portátil -> notebook; computadora/ordenador -> pc;
     audífonos -> auriculares; disco sólido -> ssd; memoria USB -> pendrive.
   - No incluyas palabras de precio, comparaciones ni relleno ("quiero", "barato", "menos de").
3. Precios en guaraníes. "millón/millones" = x 1.000.000; "mil" = x 1.000.
   "menos de / hasta X" -> precio_max. "más de / desde X" -> precio_min.
   "barato" o "económico" sin monto -> no pongas precio.
4. Intención de uso -> requisitos técnicos razonables, solo con atributos del catálogo:
   - arquitectura, diseño, edición de video, renderizado, gaming -> RAM mínima 16 GB y
     agregá a consulta_reescrita términos de hardware potente (ej. "rtx ryzen 7 core i7").
   - oficina, estudio, uso básico -> sin requisitos técnicos.
   - Comparaciones numéricas ("más de 24 pulgadas") -> minimo/maximo del atributo numérico.
5. Si la consulta nombra un tipo de producto, poné su subcategoría en categorias.
   "computadora" o "PC" sin más detalle puede ser PC armada o notebook: incluí ambas subcategorías.
6. Exclusiones ("que no sea Lenovo", "sin HP", "excepto Samsung", "ni Dell"):
   poné esas marcas en marcas_excluidas y NO las incluyas en consulta_reescrita ni en marcas.
7. Ante la duda, no pongas el filtro: es mejor mostrar de más que dejar afuera lo que el cliente busca.
8. explicacion: una frase corta, en español, dirigida al cliente, describiendo qué se buscó
   (ej. "Notebooks con 16 GB de RAM o más, hasta Gs. 5.000.000").
TXT;
    }

    private function mensaje(string $consulta, array $vocabulario): string
    {
        return "CATÁLOGO:\n"
            . json_encode($vocabulario, JSON_UNESCAPED_UNICODE)
            . "\n\nCONSULTA DEL CLIENTE:\n"
            . mb_substr($consulta, 0, 200);
    }

    /** JSON Schema de la respuesta: Gemini está obligado a cumplirlo. */
    private function esquema(): array
    {
        $numeroONulo = ['type' => ['number', 'null']];

        return [
            'type' => 'object',
            'properties' => [
                'consulta_reescrita' => ['type' => 'string'],
                'categorias'         => ['type' => 'array', 'items' => ['type' => 'integer']],
                'marcas'             => ['type' => 'array', 'items' => ['type' => 'integer']],
                'marcas_excluidas'   => ['type' => 'array', 'items' => ['type' => 'integer']],
                'precio_min'         => $numeroONulo,
                'precio_max'         => $numeroONulo,
                'especificaciones'   => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'atb_id'   => ['type' => 'integer'],
                            'minimo'   => $numeroONulo,
                            'maximo'   => $numeroONulo,
                            'opciones' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        ],
                        'required' => ['atb_id', 'minimo', 'maximo', 'opciones'],
                    ],
                ],
                'explicacion'        => ['type' => 'string'],
            ],
            'required' => [
                'consulta_reescrita', 'categorias', 'marcas', 'marcas_excluidas',
                'precio_min', 'precio_max', 'especificaciones', 'explicacion',
            ],
        ];
    }

    // =====================================================================
    // Validación de la respuesta contra el catálogo real
    // =====================================================================

    /**
     * El esquema garantiza la FORMA; esto garantiza el CONTENIDO: solo ids
     * existentes, números positivos, opciones que pertenecen a su atributo.
     */
    private function sanear(array $r, array $vocabulario): InterpretacionConsulta
    {
        $idsSubcategorias = array_column($vocabulario['subcategorias'], 'id');
        $idsMarcas = array_column($vocabulario['marcas'], 'id');
        $atributos = collect($vocabulario['atributos'])->keyBy('id');

        $soloValidos = fn ($lista, $validos) => array_values(array_unique(array_intersect(
            array_map('intval', (array) $lista), $validos
        )));

        $positivo = fn ($n) => is_numeric($n) && $n > 0 ? (float) $n : null;

        $especificaciones = collect($r['especificaciones'] ?? [])
            ->filter(fn ($e) => is_array($e) && $atributos->has((int) ($e['atb_id'] ?? 0)))
            ->map(function ($e) use ($atributos, $positivo) {
                $atributo = $atributos[(int) $e['atb_id']];

                if ($atributo['tipo'] === 'numero') {
                    return [
                        'atb_id'   => $atributo['id'],
                        'minimo'   => $positivo($e['minimo'] ?? null),
                        'maximo'   => $positivo($e['maximo'] ?? null),
                        'opciones' => [],
                    ];
                }

                $validas = array_column($atributo['opciones'] ?? [], 'id');
                return [
                    'atb_id'   => $atributo['id'],
                    'minimo'   => null,
                    'maximo'   => null,
                    'opciones' => array_values(array_intersect(array_map('intval', (array) ($e['opciones'] ?? [])), $validas)),
                ];
            })
            // Un filtro sin condición no filtra nada: se descarta.
            ->filter(fn ($e) => $e['minimo'] !== null || $e['maximo'] !== null || ! empty($e['opciones']))
            ->unique('atb_id')
            ->values()
            ->all();

        $precioMin = $positivo($r['precio_min'] ?? null);
        $precioMax = $positivo($r['precio_max'] ?? null);
        if ($precioMin && $precioMax && $precioMin > $precioMax) {
            [$precioMin, $precioMax] = [$precioMax, $precioMin];
        }

        return new InterpretacionConsulta(
            consultaReescrita: mb_substr(trim((string) ($r['consulta_reescrita'] ?? '')), 0, 120),
            categorias: $soloValidos($r['categorias'] ?? [], $idsSubcategorias),
            marcas: $soloValidos($r['marcas'] ?? [], $idsMarcas),
            precioMin: $precioMin,
            precioMax: $precioMax,
            especificaciones: $especificaciones,
            explicacion: mb_substr(trim((string) ($r['explicacion'] ?? '')), 0, 200),
            marcasExcluidas: $soloValidos($r['marcas_excluidas'] ?? [], $idsMarcas),
        );
    }
}
