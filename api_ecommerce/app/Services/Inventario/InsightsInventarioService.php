<?php
// app/Services/Inventario/InsightsInventarioService.php
namespace App\Services\Inventario;

use App\Models\Inventario\AlertaStock;
use App\Services\IA\GeminiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Explica en lenguaje natural la situación del inventario y prioriza acciones,
 * usando Gemini con salida JSON estructurada.
 *
 * División de responsabilidades:
 *   - Ciencia de Datos (Fases 2-4) CALCULA: pronóstico, SS, ROP, cantidades.
 *   - La IA generativa solo INTERPRETA esos resultados; no calcula ni decide.
 *
 * Privacidad: a la IA viajan solo agregados de productos (sin clientes).
 * Costo: se cachea y solo se regenera si cambian las alertas o el usuario lo pide.
 * Anti-alucinación: los productos que menciona la respuesta se filtran contra
 * los que realmente se enviaron.
 */
class InsightsInventarioService
{
    private const CLAVE_CACHE = 'inventario:ia:insights';
    private const HORAS_CACHE = 24;
    private const MAX_ACCIONES = 6;
    private const MAX_URGENTES = 15;
    private const MAX_SOBRESTOCK = 10;

    private const PRIORIDADES = ['alta', 'media', 'baja'];
    private const TIPOS_ACCION = ['comprar', 'liquidar', 'revisar_parametros', 'monitorear'];

    private const NOMBRE_PRODUCTO = 'COALESCE(pw.pub_titulo, LEFT(pr.prd_descri, 120))';

    public function __construct(
        private readonly GeminiClient $gemini,
        private readonly InventarioInteligenteService $inventario,
    ) {
    }

    public function disponible(): bool
    {
        return $this->gemini->disponible();
    }

    /** @return array{resumen: string, acciones: array, generado: string, desde_cache: bool}|null */
    public function generar(bool $forzar = false): ?array
    {
        $firma = $this->firmaEstado();
        $guardado = Cache::get(self::CLAVE_CACHE);

        // El caché solo vale si las alertas no cambiaron desde que se generó.
        if (! $forzar && $guardado && ($guardado['firma'] ?? null) === $firma) {
            return $guardado['resultado'] + ['desde_cache' => true];
        }

        $datos = $this->datosParaIa();
        if ($datos['totales']['alertas_pendientes'] === 0) {
            return [
                'resumen'     => 'No hay alertas pendientes: el inventario está dentro de los niveles calculados.',
                'acciones'    => [],
                'generado'    => now()->toDateTimeString(),
                'desde_cache' => false,
            ];
        }

        $respuesta = $this->gemini->generarJson(
            $this->instrucciones(),
            json_encode($datos, JSON_UNESCAPED_UNICODE),
            $this->esquema(),
        );

        if (! $respuesta) {
            return null; // un fallo no se cachea
        }

        $nombresValidos = collect($datos['reponer_urgente'])->pluck('producto')
            ->merge(collect($datos['sobrestock_mayor_capital'])->pluck('producto'))
            ->all();

        $resultado = $this->sanear($respuesta, $nombresValidos);
        Cache::put(self::CLAVE_CACHE, ['firma' => $firma, 'resultado' => $resultado], now()->addHours(self::HORAS_CACHE));

        return $resultado + ['desde_cache' => false];
    }

    /**
     * Resume el estado actual: si cambia cualquier alerta pendiente
     * (nueva, cerrada o recalculada), cambia la firma e invalida el caché.
     */
    private function firmaEstado(): string
    {
        $estado = AlertaStock::pendientes()
            ->selectRaw('COUNT(*) AS n, MAX(ast_actual) AS ultima, COALESCE(SUM(ast_sugeri), 0) AS sugerido')
            ->first();

        return md5("{$estado->n}|{$estado->ultima}|{$estado->sugerido}");
    }

    /** Solo agregados y resultados ya calculados por el módulo. */
    private function datosParaIa(): array
    {
        $resumen = $this->inventario->resumen();
        $config  = $this->inventario->config();

        $base = fn () => DB::table('alerta_stock as a')
            ->join('producto as pr', 'pr.prd_id', '=', 'a.ast_prdid')
            ->leftJoin('publicacion_web as pw', 'pw.pub_prdid', '=', 'pr.prd_id')
            ->join('stock as st', fn ($j) => $j->on('st.sto_prdid', '=', 'a.ast_prdid')->on('st.sto_depid', '=', 'a.ast_depid'))
            ->join('deposito as d', 'd.dpo_id', '=', 'a.ast_depid')
            ->where('a.ast_estado', AlertaStock::ESTADO_PENDIENTE);

        $urgentes = $base()
            ->whereIn('a.ast_tipo', [AlertaStock::TIPO_AGOTADO, AlertaStock::TIPO_CRITICO, AlertaStock::TIPO_REPONER])
            ->selectRaw(self::NOMBRE_PRODUCTO . ' AS producto, d.dpo_nombre AS deposito, a.ast_tipo AS tipo,
                         a.ast_dispon AS disponible, ROUND(a.ast_demdia * 7, 2) AS demanda_semanal,
                         a.ast_cobert AS cobertura_dias, a.ast_sugeri AS sugerido,
                         ROUND(a.ast_sugeri * st.sto_cosprm) AS inversion_gs, a.ast_ltdias AS lead_time_dias')
            ->orderByRaw("CASE a.ast_tipo WHEN 'agotado' THEN 1 WHEN 'critico' THEN 2 ELSE 3 END")
            ->orderByDesc('a.ast_demdia')
            ->limit(self::MAX_URGENTES)
            ->get();

        $sobrestock = $base()
            ->where('a.ast_tipo', AlertaStock::TIPO_SOBRESTOCK)
            ->selectRaw(self::NOMBRE_PRODUCTO . ' AS producto, d.dpo_nombre AS deposito,
                         a.ast_dispon AS disponible, ROUND(a.ast_demdia * 7, 2) AS demanda_semanal,
                         ROUND(a.ast_cobert / 7) AS cobertura_semanas,
                         ROUND(a.ast_dispon * st.sto_cosprm) AS capital_inmovilizado_gs')
            ->orderByRaw('a.ast_dispon * st.sto_cosprm DESC')
            ->limit(self::MAX_SOBRESTOCK)
            ->get();

        return [
            'parametros' => [
                'nivel_servicio_pct'      => $config['nivel_servicio'],
                'lead_time_defecto_dias'  => $config['lead_time'],
                'horizonte_semanas'       => $config['horizonte'],
                'cobertura_maxima_semanas'=> $config['cobertura_max'],
            ],
            'totales' => [
                'alertas_pendientes'      => $resumen['total_pendientes'],
                'por_tipo'                => $resumen['alertas'],
                'inversion_sugerida_gs'   => $resumen['inversion_sugerida'],
                'capital_en_sobrestock_gs'=> $resumen['valor_sobrestock'],
                'productos_analizados'    => $resumen['productos_analizados'],
            ],
            'modelos_elegidos'         => $resumen['modelos_elegidos'],
            'reponer_urgente'          => $urgentes->all(),
            'sobrestock_mayor_capital' => $sobrestock->all(),
        ];
    }

    private function instrucciones(): string
    {
        return <<<'TXT'
Sos analista de inventario de Z Informática, una tienda de informática de Capiatá (Paraguay) con pocos empleados.
Recibís en JSON el resultado de un sistema de gestión de inventario que YA calculó, con modelos estadísticos,
la demanda prevista, el stock de seguridad, el punto de reorden y la cantidad sugerida de compra de cada producto.
Tipos de alerta: agotado (sin stock), critico (bajo el stock de seguridad), reponer (alcanzó el punto de reorden),
sobrestock (stock para más semanas que la cobertura máxima).
Reglas:
1. Usá SOLO los datos recibidos. No inventes productos, cantidades ni montos. No recalcules las cantidades sugeridas.
2. En "productos" escribí los nombres EXACTAMENTE como aparecen en el JSON.
3. Priorizá: primero agotados y críticos de mayor demanda, luego el capital inmovilizado en sobrestock.
4. Cada acción debe citar el dato que la respalda y ser realizable por una tienda pequeña
   (agrupar compras por proveedor, promocionar o hacer combos con el sobrestock, revisar un lead time, etc.).
5. Si la inversión sugerida es alta, proponé un orden de compra por prioridad.
6. Español neutro, frases cortas, montos en guaraníes (Gs.).
TXT;
    }

    private function esquema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'resumen'  => ['type' => 'string'],
                'acciones' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'titulo'    => ['type' => 'string'],
                            'evidencia' => ['type' => 'string'],
                            'accion'    => ['type' => 'string'],
                            'prioridad' => ['type' => 'string', 'enum' => self::PRIORIDADES],
                            'tipo'      => ['type' => 'string', 'enum' => self::TIPOS_ACCION],
                            'productos' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['titulo', 'evidencia', 'accion', 'prioridad', 'tipo', 'productos'],
                    ],
                ],
            ],
            'required' => ['resumen', 'acciones'],
        ];
    }

    /** Valida la respuesta del modelo: nunca se confía en ella tal cual llega. */
    private function sanear(array $r, array $nombresValidos): array
    {
        $texto = fn ($v, int $max) => mb_substr(trim((string) $v), 0, $max);

        $acciones = collect($r['acciones'] ?? [])
            ->filter(fn ($a) => is_array($a) && ! empty($a['titulo']) && ! empty($a['accion']))
            ->take(self::MAX_ACCIONES)
            ->map(fn (array $a) => [
                'titulo'    => $texto($a['titulo'], 120),
                'evidencia' => $texto($a['evidencia'] ?? '', 300),
                'accion'    => $texto($a['accion'], 400),
                'prioridad' => in_array($a['prioridad'] ?? null, self::PRIORIDADES, true) ? $a['prioridad'] : 'media',
                'tipo'      => in_array($a['tipo'] ?? null, self::TIPOS_ACCION, true) ? $a['tipo'] : 'monitorear',
                // Solo productos que realmente se enviaron a la IA.
                'productos' => collect($a['productos'] ?? [])
                    ->filter(fn ($p) => is_string($p) && in_array($p, $nombresValidos, true))
                    ->unique()->take(8)->values()->all(),
            ])
            ->sortBy(fn ($a) => array_search($a['prioridad'], self::PRIORIDADES, true))
            ->values()
            ->all();

        return [
            'resumen'  => $texto($r['resumen'] ?? '', 700),
            'acciones' => $acciones,
            'generado' => now()->toDateTimeString(),
        ];
    }
}