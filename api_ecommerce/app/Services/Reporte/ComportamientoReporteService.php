<?php

namespace App\Services\Reporte;

use App\Models\Venta\VentaCab;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Análisis de comportamiento de clientes. Mismo contrato ['filas', 'totales'].
 *
 *  A. Búsquedas:     términos, búsquedas sin resultado, productos más buscados.
 *  B. Patrones:      franja horaria, comprados juntos, recompra, modalidades,
 *                    embudo y carritos abandonados.
 *  C. Preferencias:  segmentación RFM y perfil individual del cliente.
 *
 * Una compra = factura vigente (mismo criterio que Ventas, Clientes y Dashboard).
 * Fuentes nuevas: busqueda y vista_producto (ActividadClienteService).
 */
class ComportamientoReporteService extends ConsultaReporte
{
    /** Nombre completo desde persona; alias cl, pe. */
    private const NOMBRE = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', pe.per_nombr1, pe.per_nombr2, pe.per_apell1, pe.per_apell2)), ''), 'Cliente #' || cl.cli_id)";

    private const DOCUMENTO = 'COALESCE(cl.cli_ruc, pe.per_cedula)';

    /** Persona que actúa: cliente logueado o visitante anónimo. */
    private const PERSONA_BUS = 'COALESCE(b.bus_usuid::text, b.bus_visita::text)';
    private const PERSONA_VPR = 'COALESCE(v.vpr_usuid::text, v.vpr_visita::text)';

    private const DIAS_SEMANA = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    private const TRAMOS_RECOMPRA = [
        ['etiqueta' => 'Hasta 30 días',   'min' => 0,   'max' => 30],
        ['etiqueta' => '31 a 90 días',    'min' => 31,  'max' => 90],
        ['etiqueta' => '91 a 180 días',   'min' => 91,  'max' => 180],
        ['etiqueta' => 'Más de 180 días', 'min' => 181, 'max' => null],
    ];

    /**
     * RFM con umbrales fijos de Recencia y Frecuencia (no cuantiles): con pocos
     * clientes y casi todos de una sola compra, NTILE repartiría puntajes al azar
     * entre empates. El Monto sí es relativo (CUME_DIST), porque depende del catálogo.
     */
    private const RECENCIA = [30 => 5, 60 => 4, 120 => 3, 240 => 2];   // días <= clave → puntaje
    private const FRECUENCIA = [5 => 5, 3 => 4, 2 => 3];                // compras >= clave → puntaje

    public const SEGMENTOS = [
        'CAMPEON'   => ['etiqueta' => 'Mejores clientes',          'color' => 'success',   'accion' => 'Fidelizar: acceso anticipado a novedades y beneficios exclusivos.'],
        'LEAL'      => ['etiqueta' => 'Leales',             'color' => 'primary',   'accion' => 'Ofrecer productos complementarios y planes en cuotas.'],
        'NUEVO'     => ['etiqueta' => 'Nuevos',             'color' => 'info',      'accion' => 'Acompañar la primera experiencia y motivar la segunda compra.'],
        'ATENCION'  => ['etiqueta' => 'Necesitan atención', 'color' => 'warning',   'accion' => 'Recordatorio con productos vistos o de su categoría preferida.'],
        'EN_RIESGO' => ['etiqueta' => 'En riesgo',          'color' => 'danger',    'accion' => 'Campaña de reactivación con cupón antes de perderlos.'],
        'PERDIDO'   => ['etiqueta' => 'Perdidos',           'color' => 'secondary', 'accion' => 'Contacto puntual solo con ofertas de alto interés.'],
    ];

    // =====================================================================
    // A. Búsquedas
    // =====================================================================

    /** Términos más buscados, con tasa de clic (búsquedas que terminaron en un producto abierto). */
    public function busquedas(FiltrosReporte $f, ?string $origen = null): array
    {
        $clic = 'CASE WHEN EXISTS (SELECT 1 FROM vista_producto vv WHERE vv.vpr_busid = b.bus_id) THEN 1 ELSE 0 END';

        $filas = $this->busquedasDelPeriodo($f, $origen)
            ->selectRaw("b.bus_termnorm AS termino,
                COUNT(*) AS veces,
                COUNT(DISTINCT " . self::PERSONA_BUS . ") AS personas,
                SUM(CASE WHEN b.bus_total = 0 THEN 1 ELSE 0 END) AS sin_resultado,
                SUM(CASE WHEN b.bus_modo = 'ia' THEN 1 ELSE 0 END) AS con_ia,
                SUM({$clic}) AS con_clic,
                MAX(b.bus_creado) AS ultima")
            ->groupBy('b.bus_termnorm')
            ->orderByDesc('veces')
            ->limit($f->limite)
            ->get()
            ->map(fn ($r) => [
                'termino'       => $r->termino,
                'veces'         => (int) $r->veces,
                'personas'      => (int) $r->personas,
                'sin_resultado' => (int) $r->sin_resultado,
                'con_ia'        => (int) $r->con_ia,
                'tasa_clic'     => $this->porcentaje((float) $r->con_clic, (float) $r->veces),
                'ultima'        => $r->ultima,
            ])
            ->all();

        $t = $this->busquedasDelPeriodo($f, $origen)
            ->selectRaw("COUNT(*) AS total,
                COUNT(DISTINCT b.bus_termnorm) AS terminos,
                COUNT(DISTINCT " . self::PERSONA_BUS . ") AS personas,
                SUM(CASE WHEN b.bus_total = 0 THEN 1 ELSE 0 END) AS sin_resultado,
                SUM(CASE WHEN b.bus_modo = 'ia' THEN 1 ELSE 0 END) AS con_ia,
                SUM({$clic}) AS con_clic,
                SUM(CASE WHEN b.bus_origen = 'CHATBOT' THEN 1 ELSE 0 END) AS chatbot")
            ->first();

        $total = (float) $t->total;

        return [
            'filas'   => $filas,
            'totales' => [
                'busquedas'        => (int) $t->total,
                'terminos'         => (int) $t->terminos,
                'personas'         => (int) $t->personas,
                'pct_sin_resultado' => $this->porcentaje((float) $t->sin_resultado, $total),
                'pct_con_ia'       => $this->porcentaje((float) $t->con_ia, $total),
                'tasa_clic'        => $this->porcentaje((float) $t->con_clic, $total),
                'desde_chatbot'    => (int) $t->chatbot,
            ],
        ];
    }

    /** Demanda insatisfecha: lo que los clientes buscan y la tienda no tiene. */
    public function sinResultados(FiltrosReporte $f, ?string $origen = null): array
    {
        $filas = $this->busquedasDelPeriodo($f, $origen)
            ->where('b.bus_total', 0)
            ->selectRaw('b.bus_termnorm AS termino, COUNT(*) AS veces,
                COUNT(DISTINCT ' . self::PERSONA_BUS . ') AS personas,
                MIN(b.bus_creado) AS primera, MAX(b.bus_creado) AS ultima')
            ->groupBy('b.bus_termnorm')
            ->orderByDesc('veces')
            ->limit($f->limite)
            ->get()
            ->map(fn ($r) => [
                'termino'  => $r->termino,
                'veces'    => (int) $r->veces,
                'personas' => (int) $r->personas,
                'primera'  => $r->primera,
                'ultima'   => $r->ultima,
            ])
            ->all();

        return [
            'filas'   => $filas,
            'totales' => [
                'terminos' => count($filas),
                'veces'    => array_sum(array_column($filas, 'veces')),
            ],
        ];
    }

    /** Productos abiertos desde una búsqueda, con sus vistas totales y ventas del período. */
    public function productosBuscados(FiltrosReporte $f): array
    {
        $filas = DB::table('vista_producto as v')
            ->join('producto as pr', 'pr.prd_id', '=', 'v.vpr_prdid')
            ->leftJoin('publicacion_web as pw', 'pw.pub_prdid', '=', 'pr.prd_id')
            ->whereBetween('v.vpr_creado', $f->rangoTimestamps())
            ->selectRaw('pr.prd_id, ' . self::NOMBRE_PRODUCTO . ' AS producto,
                SUM(CASE WHEN v.vpr_busid IS NOT NULL THEN 1 ELSE 0 END) AS desde_busqueda,
                COUNT(*) AS vistas,
                COUNT(DISTINCT ' . self::PERSONA_VPR . ') AS personas')
            ->groupBy('pr.prd_id', 'pw.pub_titulo', 'pr.prd_descri')
            ->havingRaw('SUM(CASE WHEN v.vpr_busid IS NOT NULL THEN 1 ELSE 0 END) > 0')
            ->orderByDesc('desde_busqueda')
            ->limit($f->limite)
            ->get();

        $vendidas = $this->unidadesVendidas($f, $filas->pluck('prd_id')->all());

        return [
            'filas'   => $filas->map(fn ($r) => [
                'id'             => (int) $r->prd_id,
                'producto'       => $r->producto,
                'desde_busqueda' => (int) $r->desde_busqueda,
                'vistas'         => (int) $r->vistas,
                'personas'       => (int) $r->personas,
                'unidades'       => $u = (float) ($vendidas[$r->prd_id] ?? 0),
                'conversion'     => $this->porcentaje($u, (float) $r->personas),
            ])->all(),
            'totales' => [
                'productos'      => $filas->count(),
                'desde_busqueda' => (int) $filas->sum('desde_busqueda'),
            ],
        ];
    }

    // =====================================================================
    // B. Patrones de compra
    // =====================================================================

    /** Compras del e-commerce por día de la semana y hora (mapa de calor). */
    public function franjaHoraria(FiltrosReporte $f): array
    {
        $filas = $this->facturasVigentes($f)
            ->join('pedido as p', 'p.ped_id', '=', 'vc.ven_pedid')
            ->selectRaw('EXTRACT(ISODOW FROM p.ped_creado)::int AS dia,
                EXTRACT(HOUR FROM p.ped_creado)::int AS hora,
                COUNT(*) AS compras, SUM(vc.ven_total) AS total')
            ->groupByRaw('1, 2')
            ->orderByRaw('1, 2')
            ->get()
            ->map(fn ($r) => [
                'dia'       => (int) $r->dia,
                'dia_texto' => self::DIAS_SEMANA[(int) $r->dia],
                'hora'      => (int) $r->hora,
                'compras'   => (int) $r->compras,
                'total'     => $this->gs($r->total),
            ]);

        $porDia = $filas->groupBy('dia')->map->sum('compras');
        $porHora = $filas->groupBy('hora')->map->sum('compras');

        return [
            'filas'   => $filas->all(),
            'totales' => [
                'compras'   => (int) $filas->sum('compras'),
                'dia_pico'  => $porDia->isEmpty() ? null : self::DIAS_SEMANA[$porDia->sortDesc()->keys()->first()],
                'hora_pico' => $porHora->isEmpty() ? null : (int) $porHora->sortDesc()->keys()->first(),
                'max_celda' => (int) ($filas->max('compras') ?? 0),
            ],
        ];
    }

    /** Pares de productos que aparecen en la misma factura (análisis de canasta). */
    public function compradosJuntos(FiltrosReporte $f): array
    {
        $pares = $this->facturasVigentes($f)
            ->join('venta_detalle as a', 'a.vnd_venid', '=', 'vc.ven_id')
            ->join('venta_detalle as b', function ($j) {
                $j->on('b.vnd_venid', '=', 'vc.ven_id')->on('a.vnd_prdid', '<', 'b.vnd_prdid');
            })
            ->selectRaw('a.vnd_prdid AS a_id, b.vnd_prdid AS b_id, COUNT(DISTINCT vc.ven_id) AS veces')
            ->groupBy('a.vnd_prdid', 'b.vnd_prdid')
            ->orderByDesc('veces')
            ->limit($f->limite)
            ->get();

        $ids = $pares->pluck('a_id')->merge($pares->pluck('b_id'))->unique()->all();
        $nombres = $this->nombresProductos($ids);
        $facturasCon = $this->lineasVendidas($f)
            ->whereIn('vd.vnd_prdid', $ids ?: [0])
            ->selectRaw('vd.vnd_prdid, COUNT(DISTINCT vc.ven_id) AS facturas')
            ->groupBy('vd.vnd_prdid')
            ->pluck('facturas', 'vnd_prdid');

        $canasta = DB::query()->fromSub(
            $this->lineasVendidas($f)->selectRaw('vc.ven_id, COUNT(*) AS items')->groupBy('vc.ven_id'), 'x')
            ->selectRaw('COUNT(*) AS facturas, SUM(CASE WHEN items > 1 THEN 1 ELSE 0 END) AS multiples, AVG(items) AS promedio')
            ->first();

        return [
            'filas'   => $pares->map(fn ($r) => [
                'producto_a'   => $nombres[$r->a_id] ?? "#{$r->a_id}",
                'producto_b'   => $nombres[$r->b_id] ?? "#{$r->b_id}",
                'veces'        => (int) $r->veces,
                // De las facturas con A, qué % también llevó B (y viceversa).
                'confianza_ab' => $this->porcentaje((float) $r->veces, (float) ($facturasCon[$r->a_id] ?? 0)),
                'confianza_ba' => $this->porcentaje((float) $r->veces, (float) ($facturasCon[$r->b_id] ?? 0)),
            ])->all(),
            'totales' => [
                'facturas'           => (int) $canasta->facturas,
                'pct_multiproducto'  => $this->porcentaje((float) $canasta->multiples, (float) $canasta->facturas),
                'productos_promedio' => round((float) $canasta->promedio, 1),
            ],
        ];
    }

    /** Cada cuántos días vuelve a comprar un cliente (recompras del período). */
    public function recompra(FiltrosReporte $f): array
    {
        $dias = collect($this->intervalosRecompra($f));

        $filas = array_map(fn (array $t) => [
            'etiqueta' => $t['etiqueta'],
            'recompras' => $c = $dias->filter(fn ($d) => $d >= $t['min'] && ($t['max'] === null || $d <= $t['max']))->count(),
            'participacion' => $this->porcentaje($c, $dias->count()),
        ], self::TRAMOS_RECOMPRA);

        $compradores = (int) $this->facturasVigentes($f)->distinct()->count('vc.ven_cliid');
        $recurrentes = (int) DB::query()->fromSub($this->subRecompra($f), 'r')->distinct()->count('r.cli');

        return [
            'filas'   => $filas,
            'totales' => [
                'recompras'      => $dias->count(),
                'promedio_dias'  => $dias->isEmpty() ? null : (int) round($dias->avg()),
                'mediana_dias'   => $dias->isEmpty() ? null : (int) round($dias->median()),
                'compradores'    => $compradores,
                'recurrentes'    => $recurrentes,
                'pct_recurrentes' => $this->porcentaje($recurrentes, $compradores),
            ],
        ];
    }

    /** Contado vs crédito y envío vs retiro. */
    public function modalidades(FiltrosReporte $f): array
    {
        $condicion = $this->facturasVigentes($f)
            ->selectRaw("CASE vc.ven_condicion WHEN 2 THEN 'Crédito' ELSE 'Contado' END AS etiqueta,
                COUNT(*) AS compras, SUM(vc.ven_total) AS total")
            ->groupByRaw('1')
            ->get()
            ->map(fn ($r) => ['grupo' => 'condicion'] + (array) $r);

        $entrega = $this->facturasVigentes($f)
            ->join('pedido as p', 'p.ped_id', '=', 'vc.ven_pedid')
            ->join('tipo_entrega as te', 'te.ten_id', '=', 'p.ped_tenid')
            ->selectRaw('te.ten_descri AS etiqueta, COUNT(*) AS compras, SUM(vc.ven_total) AS total')
            ->groupBy('te.ten_descri')
            ->get()
            ->map(fn ($r) => ['grupo' => 'entrega'] + (array) $r);

        $filas = [];
        foreach (['condicion' => $condicion, 'entrega' => $entrega] as $grupo) {
            $totalGrupo = (float) $grupo->sum('compras');
            foreach ($grupo as $r) {
                $filas[] = [
                    'grupo'         => $r['grupo'],
                    'etiqueta'      => $r['etiqueta'],
                    'compras'       => (int) $r['compras'],
                    'total'         => $this->gs($r['total']),
                    'participacion' => $this->porcentaje((float) $r['compras'], $totalGrupo),
                ];
            }
        }

        return ['filas' => $filas, 'totales' => ['compras' => (int) $condicion->sum('compras')]];
    }

    /**
     * Embudo del período. Visitantes y vistas se cuentan por persona (cliente o
     * visitante anónimo); pedidos y compras, por cliente.
     */
    public function embudo(FiltrosReporte $f): array
    {
        [$desde, $hasta] = $f->rangoTimestamps();

        $visitantes = (int) DB::query()->fromSub(
            DB::table('busqueda as b')->whereBetween('b.bus_creado', [$desde, $hasta])
                ->selectRaw(self::PERSONA_BUS . ' AS k')
                ->unionAll(DB::table('vista_producto as v')->whereBetween('v.vpr_creado', [$desde, $hasta])
                    ->selectRaw(self::PERSONA_VPR . ' AS k')), 't')
            ->whereNotNull('t.k')->distinct()->count('t.k');

        $vieron = (int) DB::table('vista_producto as v')->whereBetween('v.vpr_creado', [$desde, $hasta])
            ->selectRaw('COUNT(DISTINCT ' . self::PERSONA_VPR . ') AS c')->value('c');

        $pedidos = $this->filtrarSucursalPedido(DB::table('pedido as p'), $f)
            ->whereBetween('p.ped_creado', [$desde, $hasta]);

        $conPedido = (int) (clone $pedidos)->distinct()->count('p.ped_cliid');
        $cancelados = (int) (clone $pedidos)->join('estado_pedido as ep', 'ep.est_id', '=', 'p.ped_estid')
            ->where('ep.est_codigo', 'CANCELADO')->count();

        $compraron = (int) $this->facturasVigentes($f)->whereNotNull('vc.ven_pedid')->distinct()->count('vc.ven_cliid');

        $etapas = [
            ['etapa' => 'Visitantes (buscaron o vieron productos)', 'cantidad' => $visitantes],
            ['etapa' => 'Vieron el detalle de un producto',         'cantidad' => $vieron],
            ['etapa' => 'Clientes que generaron un pedido',         'cantidad' => $conPedido],
            ['etapa' => 'Clientes que concretaron la compra',       'cantidad' => $compraron],
        ];

        $base = (float) ($etapas[0]['cantidad'] ?: 0);
        foreach ($etapas as $i => &$e) {
            $e['porcentaje'] = $this->porcentaje((float) $e['cantidad'], $base);
            $e['paso'] = $i === 0 ? null : $this->porcentaje((float) $e['cantidad'], (float) $etapas[$i - 1]['cantidad']);
        }
        unset($e);

        return [
            'filas'   => $etapas,
            'totales' => [
                'pedidos'            => (int) (clone $pedidos)->count(),
                'pedidos_cancelados' => $cancelados,
                'conversion_global'  => $this->porcentaje((float) $compraron, $base),
            ],
        ];
    }

    /**
     * Carritos sin actividad hace más de $horas (foto actual: no usa el rango).
     * El carrito se vacía al generar el pedido, así que lo que queda viejo es abandono.
     */
    public function carritosAbandonados(int $horas = 48): array
    {
        $ultima = 'MAX(COALESCE(c.car_actual, c.car_creado))';

        $filas = DB::table('carrito as c')
            ->join('usuario as u', 'u.usu_id', '=', 'c.car_idusu')
            ->join('persona as pe', 'pe.per_id', '=', 'u.usu_perid')
            ->leftJoin('cliente as cl', 'cl.cli_perid', '=', 'pe.per_id')
            ->whereNull('c.car_elimin')
            ->selectRaw('cl.cli_id, u.usu_login AS cuenta, ' . self::NOMBRE . ' AS cliente,
                COUNT(*) AS items, SUM(c.car_total) AS total, ' . $ultima . ' AS ultima')
            ->groupBy('cl.cli_id', 'u.usu_login', 'pe.per_nombr1', 'pe.per_nombr2', 'pe.per_apell1', 'pe.per_apell2')
            ->havingRaw("{$ultima} < NOW() - make_interval(hours => ?)", [$horas])
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'cliente_id' => $r->cli_id ? (int) $r->cli_id : null,
                'cliente'    => $r->cliente,
                'cuenta'     => $r->cuenta,
                'items'      => (int) $r->items,
                'total'      => $this->gs($r->total),
                'ultima'     => $r->ultima,
                'dias'       => (int) now()->diffInDays($r->ultima),
            ]);

        return [
            'filas'   => $filas->all(),
            'totales' => [
                'carritos' => $filas->count(),
                'items'    => (int) $filas->sum('items'),
                'valor'    => (int) $filas->sum('total'),
                'horas'    => $horas,
            ],
        ];
    }

    // =====================================================================
    // C. Preferencias: segmentación y perfil individual
    // =====================================================================

    /** Clientes del período con su segmento RFM. */
    public function segmentos(FiltrosReporte $f): array
    {
        $rfm = $this->rfm($f);
        $datos = $this->datosClientes($rfm->keys()->all());

        $filas = $rfm->map(fn (array $r, int $id) => [
            'id'       => $id,
            'cliente'  => $datos[$id]->cliente ?? "Cliente #{$id}",
            'documento' => $datos[$id]->documento ?? null,
        ] + $r)->sortByDesc('total')->values();

        $resumen = collect(self::SEGMENTOS)->map(fn (array $s, string $clave) => [
            'clave'    => $clave,
            'etiqueta' => $s['etiqueta'],
            'color'    => $s['color'],
            'accion'   => $s['accion'],
            'clientes' => $c = $filas->where('segmento', $clave)->count(),
            'total'    => (int) $filas->where('segmento', $clave)->sum('total'),
            'participacion' => $this->porcentaje($c, $filas->count()),
        ])->values()->all();

        return [
            'filas'   => $filas->all(),
            'totales' => ['clientes' => $filas->count(), 'segmentos' => $resumen],
        ];
    }

    /** Buscador del selector de cliente (nombre, cédula o RUC). */
    public function buscarClientes(string $q): array
    {
        $like = '%' . mb_strtolower(trim($q)) . '%';

        return DB::table('cliente as cl')
            ->join('persona as pe', 'pe.per_id', '=', 'cl.cli_perid')
            ->where(fn (Builder $w) => $w
                ->whereRaw('LOWER(' . self::NOMBRE . ') LIKE ?', [$like])
                ->orWhere('pe.per_cedula', 'like', $like)
                ->orWhere('cl.cli_ruc', 'like', $like))
            ->selectRaw('cl.cli_id AS id, ' . self::NOMBRE . ' AS nombre, ' . self::DOCUMENTO . ' AS documento')
            ->orderBy('nombre')
            ->limit(15)
            ->get()
            ->all();
    }

    /** Perfil de comportamiento de UN cliente. */
    public function perfilCliente(int $clienteId, FiltrosReporte $f): array
    {
        $datos = DB::table('cliente as cl')
            ->join('persona as pe', 'pe.per_id', '=', 'cl.cli_perid')
            ->leftJoin('tipo_cliente as tc', 'tc.tpc_id', '=', 'cl.cli_tpcid')
            ->leftJoin('usuario as u', 'u.usu_perid', '=', 'pe.per_id')
            ->where('cl.cli_id', $clienteId)
            ->selectRaw('cl.cli_id, ' . self::NOMBRE . ' AS cliente, ' . self::DOCUMENTO . " AS documento,
                COALESCE(tc.tpc_descri, '—') AS tipo, cl.cli_creado AS registrado,
                u.usu_id, u.usu_login AS cuenta")
            ->first();

        abort_if(! $datos, 404, 'Cliente no encontrado.');

        $usuarioId = $datos->usu_id ? (int) $datos->usu_id : null;
        $rfm = $this->rfm($f)->get($clienteId);
        $intervalos = $this->intervalosRecompra($f, $clienteId);
        $promedio = $intervalos ? (int) round(array_sum($intervalos) / count($intervalos)) : null;

        return [
            'cliente' => [
                'id'         => (int) $datos->cli_id,
                'nombre'     => $datos->cliente,
                'documento'  => $datos->documento,
                'tipo'       => $datos->tipo,
                'registrado' => $datos->registrado,
                'cuenta'     => $datos->cuenta,
                'tiene_cuenta_web' => $usuarioId !== null,
            ],
            'rfm' => $rfm,
            'segmento' => $rfm ? ['clave' => $rfm['segmento']] + self::SEGMENTOS[$rfm['segmento']] : null,
            'recompra' => [
                'promedio_dias'    => $promedio,
                'proxima_estimada' => ($promedio && $rfm) ? Carbon::parse($rfm['ultima'])->addDays($promedio)->toDateString() : null,
            ],
            'categorias'  => $this->preferenciaPorCliente($f, $clienteId, 'categoria'),
            'marcas'      => $this->preferenciaPorCliente($f, $clienteId, 'marca'),
            'productos'   => $this->productosDelCliente($f, $clienteId),
            'precios'     => $this->rangoPrecioCliente($f, $clienteId),
            'habitos'     => $this->habitosCliente($f, $clienteId),
            'busquedas'   => $usuarioId ? $this->busquedasDelUsuario($f, $usuarioId) : [],
            'vistos_sin_comprar' => $usuarioId ? $this->vistosSinComprar($f, $usuarioId, $clienteId) : [],
            'favoritos'   => $usuarioId ? $this->favoritosDelUsuario($usuarioId, $clienteId) : [],
            'carrito'     => $usuarioId ? $this->carritoDelUsuario($usuarioId) : null,
            'sugeridos'   => $this->complementariosParaCliente($f, $clienteId),
        ];
    }

    /** Datos agregados y anónimos que se envían a la IA (sin nombres ni documentos). */
    public function resumenParaIa(FiltrosReporte $f): array
    {
        $corto = new FiltrosReporte($f->desde, $f->hasta, $f->sucursalId, null, $f->agrupacion, 10);

        return [
            'periodo'           => $f->toArray(),
            'busquedas'         => $this->busquedas($corto),
            'sin_resultados'    => $this->sinResultados($corto),
            'productos_buscados' => $this->productosBuscados($corto),
            'franja_horaria'    => $this->franjaHoraria($corto)['totales'],
            'comprados_juntos'  => $this->compradosJuntos($corto),
            'recompra'          => $this->recompra($corto),
            'modalidades'       => $this->modalidades($corto)['filas'],
            'embudo'            => $this->embudo($corto),
            'carritos_abandonados' => $this->carritosAbandonados()['totales'],
            'segmentos'         => $this->segmentos($corto)['totales']['segmentos'],
        ];
    }

    /** Perfil sin datos personales, para la sugerencia de la IA sobre un cliente. */
    public function perfilAnonimo(int $clienteId, FiltrosReporte $f): array
    {
        $p = $this->perfilCliente($clienteId, $f);
        unset($p['cliente']);

        return $p;
    }

    // =====================================================================
    // Auxiliares de búsqueda y RFM
    // =====================================================================

    private function busquedasDelPeriodo(FiltrosReporte $f, ?string $origen): Builder
    {
        return DB::table('busqueda as b')
            ->whereBetween('b.bus_creado', $f->rangoTimestamps())
            ->when($origen, fn (Builder $q, string $o) => $q->where('b.bus_origen', $o));
    }

    /** @return Collection<int, array> clave = cli_id */
    private function rfm(FiltrosReporte $f): Collection
    {
        $hasta = $f->hasta->toDateString();

        $filas = $this->facturasVigentes($f)
            ->selectRaw('vc.ven_cliid AS cli_id, MAX(vc.ven_fchaem) AS ultima, MIN(vc.ven_fchaem) AS primera,
                COUNT(*) AS compras, SUM(vc.ven_total) AS total')
            ->groupBy('vc.ven_cliid');

        return DB::query()->fromSub($filas, 'x')
            ->selectRaw('x.*, (CAST(? AS date) - x.ultima) AS dias, CEIL(CUME_DIST() OVER (ORDER BY x.total) * 5) AS m', [$hasta])
            ->get()
            ->mapWithKeys(function ($r) {
                $dias = (int) $r->dias;
                $compras = (int) $r->compras;
                $rr = $this->puntaje($dias, self::RECENCIA, fn ($v, $umbral) => $v <= $umbral);
                $ff = $this->puntaje($compras, self::FRECUENCIA, fn ($v, $umbral) => $v >= $umbral);

                return [(int) $r->cli_id => [
                    'primera'   => $r->primera,
                    'ultima'    => $r->ultima,
                    'dias'      => $dias,
                    'compras'   => $compras,
                    'total'     => $this->gs($r->total),
                    'ticket'    => $compras > 0 ? $this->gs($r->total / $compras) : 0,
                    'r'         => $rr,
                    'f'         => $ff,
                    'm'         => (int) $r->m,
                    'alto_valor' => (int) $r->m >= 4,
                    'segmento'  => $this->segmentoDe($rr, $ff),
                ]];
            });
    }

    private function puntaje(int $valor, array $umbrales, callable $cumple): int
    {
        foreach ($umbrales as $umbral => $puntos) {
            if ($cumple($valor, $umbral)) {
                return $puntos;
            }
        }

        return 1;
    }

    private function segmentoDe(int $r, int $f): string
    {
        return match (true) {
            $r >= 4 && $f >= 4 => 'CAMPEON',
            $r >= 3 && $f >= 3 => 'LEAL',
            $r >= 4            => 'NUEVO',
            $r === 3           => 'ATENCION',
            $f >= 3            => 'EN_RIESGO',
            default            => 'PERDIDO',
        };
    }

    /** Facturas con la fecha de la compra anterior del mismo cliente. Alias de salida: cli, dias. */
    private function subRecompra(FiltrosReporte $f, ?int $clienteId = null): Builder
    {
        [$desde, $hasta] = $f->rangoFechas();

        $conAnterior = DB::table('venta_cab as vc')
            ->where('vc.ven_estado', '<>', VentaCab::ESTADO_ANULADA)
            ->where('vc.ven_fchaem', '<=', $hasta)
            ->when($f->sucursalId, fn (Builder $q, int $id) => $q->where('vc.ven_sucid', $id))
            ->when($clienteId, fn (Builder $q, int $id) => $q->where('vc.ven_cliid', $id))
            ->selectRaw('vc.ven_cliid AS cli, vc.ven_fchaem AS fecha,
                LAG(vc.ven_fchaem) OVER (PARTITION BY vc.ven_cliid ORDER BY vc.ven_fchaem, vc.ven_id) AS anterior');

        return DB::query()->fromSub($conAnterior, 'h')
            ->whereNotNull('h.anterior')
            ->where('h.fecha', '>=', $desde)
            ->selectRaw('h.cli, (h.fecha - h.anterior) AS dias');
    }

    /** @return int[] días entre compras consecutivas */
    private function intervalosRecompra(FiltrosReporte $f, ?int $clienteId = null): array
    {
        return $this->subRecompra($f, $clienteId)->pluck('dias')->map(fn ($d) => (int) $d)->all();
    }

    // =====================================================================
    // Auxiliares del perfil individual
    // =====================================================================

    private function lineasDelCliente(FiltrosReporte $f, int $clienteId): Builder
    {
        return $this->lineasConProducto($f)->where('vc.ven_cliid', $clienteId);
    }

    /** @param string $por 'categoria' | 'marca' */
    private function preferenciaPorCliente(FiltrosReporte $f, int $clienteId, string $por): array
    {
        $q = $this->lineasDelCliente($f, $clienteId);

        if ($por === 'categoria') {
            $q->leftJoin('categoria as ca', 'ca.cat_id', '=', DB::raw(self::CATEGORIA_PRODUCTO))
                ->selectRaw("COALESCE(ca.cat_nombre, 'Sin categoría') AS etiqueta")
                ->groupBy('ca.cat_nombre');
        } else {
            $q->leftJoin('marca as ma', 'ma.mar_id', '=', 'pr.prd_marid')
                ->selectRaw("COALESCE(ma.mar_descri, 'Sin marca') AS etiqueta")
                ->groupBy('ma.mar_descri');
        }

        $filas = $q->selectRaw('SUM(vd.vnd_cantidad) AS unidades, SUM(vd.vnd_valorventa) AS total')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $total = (float) $filas->sum('total');

        return $filas->map(fn ($r) => [
            'etiqueta'      => $r->etiqueta,
            'unidades'      => (float) $r->unidades,
            'total'         => $this->gs($r->total),
            'participacion' => $this->porcentaje((float) $r->total, $total),
        ])->all();
    }

    private function productosDelCliente(FiltrosReporte $f, int $clienteId): array
    {
        return $this->lineasDelCliente($f, $clienteId)
            ->selectRaw(self::NOMBRE_PRODUCTO . ' AS producto, COUNT(DISTINCT vc.ven_id) AS veces,
                SUM(vd.vnd_cantidad) AS unidades, SUM(vd.vnd_valorventa) AS total, MAX(vc.ven_fchaem) AS ultima')
            ->groupBy('pr.prd_id', 'pw.pub_titulo', 'pr.prd_descri')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'producto' => $r->producto,
                'veces'    => (int) $r->veces,
                'unidades' => (float) $r->unidades,
                'total'    => $this->gs($r->total),
                'ultima'   => $r->ultima,
            ])
            ->all();
    }

    private function rangoPrecioCliente(FiltrosReporte $f, int $clienteId): ?array
    {
        $r = $this->lineasDelCliente($f, $clienteId)
            ->selectRaw('MIN(vd.vnd_precio) AS minimo, AVG(vd.vnd_precio) AS promedio, MAX(vd.vnd_precio) AS maximo')
            ->first();

        return $r && $r->maximo !== null
            ? ['minimo' => $this->gs($r->minimo), 'promedio' => $this->gs($r->promedio), 'maximo' => $this->gs($r->maximo)]
            : null;
    }

    /** Lo que más repite el cliente: condición, entrega, método de pago, día y franja. */
    private function habitosCliente(FiltrosReporte $f, int $clienteId): array
    {
        $base = fn () => $this->facturasVigentes($f)->where('vc.ven_cliid', $clienteId);
        $moda = fn (Builder $q, string $expr) => $q->selectRaw("{$expr} AS valor, COUNT(*) AS veces")
            ->groupByRaw('1')->orderByDesc('veces')->limit(1)->first();

        $conPedido = fn () => $base()->join('pedido as p', 'p.ped_id', '=', 'vc.ven_pedid');

        $condicion = $moda($base(), "CASE vc.ven_condicion WHEN 2 THEN 'Crédito' ELSE 'Contado' END");
        $entrega = $moda($conPedido()->join('tipo_entrega as te', 'te.ten_id', '=', 'p.ped_tenid'), 'te.ten_descri');
        $metodo = $moda($conPedido()->join('metodo_pago as mp', 'mp.mpa_id', '=', 'p.ped_mpaid'), 'mp.mpa_descri');
        $dia = $moda($conPedido(), 'EXTRACT(ISODOW FROM p.ped_creado)::int');
        $franja = $moda($conPedido(), "CASE WHEN EXTRACT(HOUR FROM p.ped_creado) < 12 THEN 'Mañana'
            WHEN EXTRACT(HOUR FROM p.ped_creado) < 19 THEN 'Tarde' ELSE 'Noche' END");

        return [
            'condicion'   => $condicion?->valor,
            'entrega'     => $entrega?->valor,
            'metodo_pago' => $metodo?->valor,
            'dia'         => $dia ? self::DIAS_SEMANA[(int) $dia->valor] : null,
            'franja'      => $franja?->valor,
        ];
    }

    private function busquedasDelUsuario(FiltrosReporte $f, int $usuarioId): array
    {
        return DB::table('busqueda as b')
            ->where('b.bus_usuid', $usuarioId)
            ->whereBetween('b.bus_creado', $f->rangoTimestamps())
            ->selectRaw('b.bus_termnorm AS termino, COUNT(*) AS veces, MAX(b.bus_total) AS resultados, MAX(b.bus_creado) AS ultima')
            ->groupBy('b.bus_termnorm')
            ->orderByDesc('ultima')
            ->limit(10)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /** Productos que el cliente miró en el período y no compró: oportunidad de venta. */
    private function vistosSinComprar(FiltrosReporte $f, int $usuarioId, int $clienteId): array
    {
        $comprados = $this->lineasVendidas($f)->where('vc.ven_cliid', $clienteId)->select('vd.vnd_prdid');

        return DB::table('vista_producto as v')
            ->join('producto as pr', 'pr.prd_id', '=', 'v.vpr_prdid')
            ->leftJoin('publicacion_web as pw', 'pw.pub_prdid', '=', 'pr.prd_id')
            ->where('v.vpr_usuid', $usuarioId)
            ->whereBetween('v.vpr_creado', $f->rangoTimestamps())
            ->whereNotIn('v.vpr_prdid', $comprados)
            ->selectRaw(self::NOMBRE_PRODUCTO . ' AS producto, COUNT(*) AS vistas, MAX(v.vpr_creado) AS ultima')
            ->groupBy('pr.prd_id', 'pw.pub_titulo', 'pr.prd_descri')
            ->orderByDesc('vistas')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['producto' => $r->producto, 'vistas' => (int) $r->vistas, 'ultima' => $r->ultima])
            ->all();
    }

    private function favoritosDelUsuario(int $usuarioId, int $clienteId): array
    {
        $compradosAlgunaVez = DB::table('venta_cab as vc')
            ->join('venta_detalle as vd', 'vd.vnd_venid', '=', 'vc.ven_id')
            ->where('vc.ven_cliid', $clienteId)
            ->where('vc.ven_estado', '<>', VentaCab::ESTADO_ANULADA)
            ->whereNotNull('vd.vnd_prdid')
            ->pluck('vd.vnd_prdid')->flip();

        return DB::table('favorito as fa')
            ->join('producto as pr', 'pr.prd_id', '=', 'fa.fav_idpro')
            ->leftJoin('publicacion_web as pw', 'pw.pub_prdid', '=', 'pr.prd_id')
            ->where('fa.fav_idusu', $usuarioId)
            ->selectRaw('pr.prd_id, ' . self::NOMBRE_PRODUCTO . ' AS producto, fa.fav_creado AS desde')
            ->orderByDesc('fa.fav_creado')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'producto' => $r->producto,
                'desde'    => $r->desde,
                'comprado' => $compradosAlgunaVez->has($r->prd_id),
            ])
            ->all();
    }

    private function carritoDelUsuario(int $usuarioId): ?array
    {
        $r = DB::table('carrito as c')
            ->where('c.car_idusu', $usuarioId)
            ->whereNull('c.car_elimin')
            ->selectRaw('COUNT(*) AS items, SUM(c.car_total) AS total, MAX(COALESCE(c.car_actual, c.car_creado)) AS ultima')
            ->first();

        return $r && (int) $r->items > 0
            ? ['items' => (int) $r->items, 'total' => $this->gs($r->total), 'ultima' => $r->ultima]
            : null;
    }

    /** Lo que otros clientes compraron junto con lo que él compró, y él todavía no tiene. */
    private function complementariosParaCliente(FiltrosReporte $f, int $clienteId): array
    {
        $mios = DB::table('venta_cab as vc')
            ->join('venta_detalle as vd', 'vd.vnd_venid', '=', 'vc.ven_id')
            ->where('vc.ven_cliid', $clienteId)
            ->where('vc.ven_estado', '<>', VentaCab::ESTADO_ANULADA)
            ->whereNotNull('vd.vnd_prdid')
            ->distinct()
            ->pluck('vd.vnd_prdid')
            ->all();

        if (! $mios) {
            return [];
        }

        $filas = $this->facturasVigentes($f)
            ->join('venta_detalle as a', 'a.vnd_venid', '=', 'vc.ven_id')
            ->join('venta_detalle as b', function ($j) {
                $j->on('b.vnd_venid', '=', 'vc.ven_id')->on('b.vnd_prdid', '<>', 'a.vnd_prdid');
            })
            ->where('vc.ven_cliid', '<>', $clienteId)
            ->whereIn('a.vnd_prdid', $mios)
            ->whereNotIn('b.vnd_prdid', $mios)
            ->selectRaw('b.vnd_prdid AS id, COUNT(DISTINCT vc.ven_id) AS veces')
            ->groupBy('b.vnd_prdid')
            ->orderByDesc('veces')
            ->limit(5)
            ->get();

        $nombres = $this->nombresProductos($filas->pluck('id')->all());

        return $filas->map(fn ($r) => [
            'producto' => $nombres[$r->id] ?? "#{$r->id}",
            'veces'    => (int) $r->veces,
        ])->all();
    }

    // =====================================================================
    // Auxiliares generales
    // =====================================================================

    /** @return array<int, string> */
    private function nombresProductos(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        return DB::table('producto as pr')
            ->leftJoin('publicacion_web as pw', 'pw.pub_prdid', '=', 'pr.prd_id')
            ->whereIn('pr.prd_id', $ids)
            ->selectRaw('pr.prd_id, ' . self::NOMBRE_PRODUCTO . ' AS nombre')
            ->pluck('nombre', 'prd_id')
            ->all();
    }

    /** @return array<int, float> unidades vendidas por producto en el período */
    private function unidadesVendidas(FiltrosReporte $f, array $ids): array
    {
        if (! $ids) {
            return [];
        }

        return $this->lineasVendidas($f)
            ->whereIn('vd.vnd_prdid', $ids)
            ->selectRaw('vd.vnd_prdid, SUM(vd.vnd_cantidad) AS unidades')
            ->groupBy('vd.vnd_prdid')
            ->pluck('unidades', 'vnd_prdid')
            ->all();
    }

    /** @return array<int, object> clave = cli_id */
    private function datosClientes(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        return DB::table('cliente as cl')
            ->join('persona as pe', 'pe.per_id', '=', 'cl.cli_perid')
            ->whereIn('cl.cli_id', $ids)
            ->selectRaw('cl.cli_id, ' . self::NOMBRE . ' AS cliente, ' . self::DOCUMENTO . ' AS documento')
            ->get()
            ->keyBy('cli_id')
            ->all();
    }
}