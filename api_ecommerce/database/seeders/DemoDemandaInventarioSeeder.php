<?php
// database/seeders/DemoDemandaInventarioSeeder.php
namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Datos DEMO de demanda realista para validar la Gestión inteligente de inventario.
 *
 * Genera ventas de mostrador (venta_cab/venta_detalle) durante SEMANAS semanas,
 * asignando a cada producto un perfil de rotación:
 *
 *   alta  (15 %)  accesorios y consumibles: venden todas las semanas.
 *   media (25 %)  periféricos: venden casi todas las semanas.
 *   baja  (60 %)  equipos y componentes caros: demanda intermitente.
 *
 * La cantidad semanal sigue una distribución de Poisson (modelo estándar para
 * conteos de ventas), con tendencia propia por producto y estacionalidad
 * mensual de Paraguay (vuelta a clases en feb-mar, Black Friday y fin de año).
 *
 * Requisitos: haber corrido DemoMetricasSeeder (usa sus clientes DEMO).
 * Todo queda marcado con ven_obs / tim_observacion = MARCA.
 * Limpieza: php artisan db:seed --class=LimpiarDemoInventarioSeeder
 *
 * Exporta el perfil real de cada producto a storage/app/demo_perfiles_demanda.csv
 * para comparar luego contra la clasificación y el modelo elegido por el sistema.
 */
class DemoDemandaInventarioSeeder extends Seeder
{
    public const MARCA = 'DEMO-INVENTARIO';

    private const SEMANAS = 60;
    private const SEMILLA = 4040;   // misma semilla = mismos datos

    /** porcion del catálogo · λ = unidades/semana promedio · tendencia total en el período. */
    private const PERFILES = [
        'alta'  => ['porcion' => 0.15, 'lambda' => [4.0, 10.0],  'tendencia' => [0.00, 0.30]],
        'media' => ['porcion' => 0.25, 'lambda' => [0.8, 2.5],   'tendencia' => [-0.10, 0.20]],
        'baja'  => ['porcion' => 0.60, 'lambda' => [0.08, 0.40], 'tendencia' => [-0.30, 0.10]],
    ];

    /** Factor por mes (los meses ausentes valen 1.0). */
    private const ESTACIONALIDAD = [1 => 0.85, 2 => 1.30, 3 => 1.15, 11 => 1.20, 12 => 1.50];

    /** Peso relativo de cada día (lunes..domingo) dentro de la semana. */
    private const PESO_DIA = [16, 16, 16, 16, 18, 14, 4];

    private array $ctx = [];
    private array $numeros = [];
    private int $facturas = 0;
    private float $unidades = 0;

    public function run(): void
    {
        if (DB::table('venta_cab')->where('ven_obs', self::MARCA)->exists()) {
            $this->command->warn('Ya existen ventas ' . self::MARCA . '. Ejecutá primero: php artisan db:seed --class=LimpiarDemoInventarioSeeder');
            return;
        }

        mt_srand(self::SEMILLA);

        DB::transaction(function () {
            $this->ctx = $this->cargarContexto();
            $perfiles  = $this->asignarPerfiles($this->ctx['productos']);

            $this->generarVentas($perfiles);
            $this->actualizarTimbrados();
            $archivo = $this->exportarPerfiles($perfiles);

            $conteo = array_count_values(array_column($perfiles, 'perfil'));
            $this->command->info('Demanda demo generada:');
            $this->command->table(['Concepto', 'Valor'], [
                ['Semanas', self::SEMANAS],
                ['Productos alta rotación', $conteo['alta'] ?? 0],
                ['Productos media rotación', $conteo['media'] ?? 0],
                ['Productos baja rotación', $conteo['baja'] ?? 0],
                ['Facturas', $this->facturas],
                ['Unidades vendidas', $this->unidades],
            ]);
            $this->command->line("Perfiles exportados en: {$archivo}");
            $this->command->warn('Ejecutá "php artisan cache:clear" para refrescar los reportes.');
        });
    }

    // =====================================================================
    // Contexto
    // =====================================================================

    private function cargarContexto(): array
    {
        $usuario = DB::table('usuario')->orderBy('usu_id')->value('usu_id')
            ?? throw new RuntimeException('No hay usuarios: ejecutá primero los seeders base.');
        $empresa = DB::table('empresa')->first()
            ?? throw new RuntimeException('No hay empresa configurada.');
        $tcoFactura = DB::table('tipo_comprobante')->where('tco_codigo', '001')->value('tco_id')
            ?? throw new RuntimeException('Falta el tipo de comprobante 001 (Factura).');

        $clientes = DB::table('cliente as c')
            ->join('persona as p', 'p.per_id', '=', 'c.cli_perid')
            ->where('p.per_cedula', 'like', 'DEMO-%')
            ->get(['c.cli_id', 'p.per_nombr1', 'p.per_apell1', 'p.per_cedula'])
            ->all();
        if (! $clientes) {
            throw new RuntimeException('No hay clientes DEMO: ejecutá primero DemoMetricasSeeder.');
        }

        $inicio = $this->primerLunes();
        $sucursales = [];
        foreach (DB::table('sucursal')->where('suc_estado', 1)->orderByDesc('suc_princi')->get() as $s) {
            $caja = DB::table('caja')->where('caj_sucid', $s->suc_id)->where('caj_estado', 1)
                ->orderByDesc('caj_tipo')->first();
            $deposito = DB::table('deposito')->where('dpo_sucid', $s->suc_id)
                ->where('dpo_princi', 1)->where('dpo_estado', 1)->value('dpo_id');
            if (! $caja || ! $deposito) {
                continue;
            }

            // Timbrado propio e INACTIVO: no consume la numeración real.
            $timId = DB::table('timbrado')->insertGetId([
                'tim_empid'       => $empresa->emp_id,
                'tim_tcoid'       => $tcoFactura,
                'tim_sucid'       => $s->suc_id,
                'tim_numero'      => '98' . str_pad((string) $s->suc_id, 6, '0', STR_PAD_LEFT),
                'tim_establec'    => DB::table('timbrado')->where('tim_sucid', $s->suc_id)->value('tim_establec') ?? '001',
                'tim_puntexp'     => $caja->caj_puntexp,
                'tim_fechin'      => $inicio->toDateString(),
                'tim_fechfn'      => now()->addYear()->toDateString(),
                'tim_nroini'      => 1,
                'tim_nrofin'      => 9999999,
                'tim_nroact'      => 0,
                'tim_observacion' => self::MARCA,
                'tim_estado'      => 0,
            ], 'tim_id');

            $sucursales[] = [
                'id'        => (int) $s->suc_id,
                'principal' => (bool) $s->suc_princi,
                'caja'      => $caja,
                'timbrado'  => DB::table('timbrado')->where('tim_id', $timId)->first(),
            ];
            $this->numeros[$timId] = 0;
        }
        if (! $sucursales) {
            throw new RuntimeException('Ninguna sucursal activa tiene caja y depósito principal.');
        }

        $productos = DB::table('producto as pr')
            ->join('stock as st', 'st.sto_prdid', '=', 'pr.prd_id')
            ->leftJoin('tipo_iva as ti', 'ti.tiv_id', '=', 'pr.prd_tivid')
            ->leftJoin('publicacion_web as pw', 'pw.pub_prdid', '=', 'pr.prd_id')
            ->where('pr.prd_estado', 1)
            ->where('pr.prd_afestk', 1)
            ->where('pr.prd_pvtmin', '>', 0)
            ->groupBy('pr.prd_id', 'pr.prd_codinv', 'pw.pub_titulo', 'pr.prd_descri', 'pr.prd_pvtmin', 'ti.tiv_tasa')
            ->selectRaw("pr.prd_id, pr.prd_codinv,
                LEFT(COALESCE(pw.pub_titulo, pr.prd_descri), 140) AS nombre,
                pr.prd_pvtmin AS precio, COALESCE(ti.tiv_tasa, 10) AS tasa,
                MAX(st.sto_cosprm) AS costo")
            ->get()
            ->map(function ($p) {
                $precio = (float) $p->precio;
                $tasa   = in_array((float) $p->tasa, [0.0, 5.0, 10.0], true) ? (float) $p->tasa : 10.0;
                $costo  = (float) $p->costo > 0 ? (float) $p->costo : round($precio / (1 + $tasa / 100) / 1.3, 4);

                return ['id' => (int) $p->prd_id, 'codigo' => $p->prd_codinv, 'nombre' => $p->nombre,
                        'precio' => $precio, 'tasa' => $tasa, 'costo' => $costo];
            })
            ->all();
        if (count($productos) < 10) {
            throw new RuntimeException('Se necesitan al menos 10 productos activos con precio y stock.');
        }

        return compact('usuario', 'empresa', 'clientes', 'sucursales', 'productos');
    }

    // =====================================================================
    // Perfiles de rotación
    // =====================================================================

    /**
     * Los productos más baratos tienden a rotar más (accesorios), pero con
     * ruido para que el precio no determine el perfil por sí solo.
     *
     * @return array<int, array{producto: array, perfil: string, lambda: float, tendencia: float}>
     */
    private function asignarPerfiles(array $productos): array
    {
        $ordenados = array_map(fn ($p) => ['p' => $p, 'orden' => $p['precio'] * $this->azar(0.5, 1.5)], $productos);
        usort($ordenados, fn ($a, $b) => $a['orden'] <=> $b['orden']);

        $total  = count($ordenados);
        $limite = [
            'alta'  => (int) round($total * self::PERFILES['alta']['porcion']),
            'media' => (int) round($total * (self::PERFILES['alta']['porcion'] + self::PERFILES['media']['porcion'])),
        ];

        $perfiles = [];
        foreach ($ordenados as $i => $fila) {
            $perfil = $i < $limite['alta'] ? 'alta' : ($i < $limite['media'] ? 'media' : 'baja');
            $conf   = self::PERFILES[$perfil];

            $perfiles[] = [
                'producto'  => $fila['p'],
                'perfil'    => $perfil,
                'lambda'    => round($this->azar(...$conf['lambda']), 3),
                'tendencia' => round($this->azar(...$conf['tendencia']), 3),
            ];
        }

        return $perfiles;
    }

    // =====================================================================
    // Ventas
    // =====================================================================

    private function generarVentas(array $perfiles): void
    {
        $hoy    = CarbonImmutable::now();
        $inicio = $this->primerLunes();

        for ($w = 0; $w < self::SEMANAS; $w++) {
            $lunes   = $inicio->addWeeks($w);
            $factor  = self::ESTACIONALIDAD[$lunes->addDays(3)->month] ?? 1.0;
            $diasMax = $lunes->isSameWeek($hoy) ? $lunes->diffInDays($hoy->startOfDay()) + 1 : 7;
            $avance  = $w / (self::SEMANAS - 1);

            /** @var array<int, array<int, array{p: array, cant: int}>> $porDia */
            $porDia = [];
            foreach ($perfiles as $perfil) {
                $lambda = $perfil['lambda'] * (1 + $perfil['tendencia'] * $avance) * $factor * ($diasMax / 7);
                $cantidad = $this->poisson(max(0.0, $lambda));

                while ($cantidad > 0) {
                    $unidades  = min($cantidad, mt_rand(1, 100) <= 85 ? 1 : 2);
                    $cantidad -= $unidades;
                    $porDia[$this->elegirDia($diasMax)][] = ['p' => $perfil['producto'], 'cant' => $unidades];
                }
            }

            foreach ($porDia as $dia => $lineas) {
                shuffle($lineas);
                while ($lineas) {
                    $factura = array_splice($lineas, 0, mt_rand(1, 100) <= 70 ? 1 : mt_rand(2, 3));
                    $fecha   = $lunes->addDays($dia)->setTime(mt_rand(8, 20), mt_rand(0, 59));
                    $this->crearFactura($this->elegirSucursal(), $factura, $fecha->min($hoy));
                }
            }
        }
    }

    /** @param array<int, array{p: array, cant: int}> $lineas */
    private function crearFactura(array $suc, array $lineas, CarbonImmutable $fecha): void
    {
        $tim     = $suc['timbrado'];
        $numero  = ++$this->numeros[$tim->tim_id];
        $cliente = $this->ctx['clientes'][mt_rand(0, count($this->ctx['clientes']) - 1)];

        $tot = ['exenta' => 0.0, 'grav5' => 0.0, 'grav10' => 0.0, 'iva5' => 0.0, 'iva10' => 0.0];
        foreach ($lineas as &$l) {
            $l['total'] = round($l['p']['precio'] * $l['cant'], 2);
            $l['iva']   = $l['p']['tasa'] > 0 ? round($l['total'] * $l['p']['tasa'] / (100 + $l['p']['tasa']), 2) : 0.0;

            match ((int) $l['p']['tasa']) {
                5       => [$tot['grav5'] += $l['total'], $tot['iva5'] += $l['iva']],
                10      => [$tot['grav10'] += $l['total'], $tot['iva10'] += $l['iva']],
                default => $tot['exenta'] += $l['total'],
            };
        }
        unset($l);
        $subtotal = $tot['exenta'] + $tot['grav5'] + $tot['grav10'];

        $ventaId = DB::table('venta_cab')->insertGetId([
            'ven_timid'       => $tim->tim_id,
            'ven_cajid'       => $suc['caja']->caj_id,
            'ven_cliid'       => $cliente->cli_id,
            'ven_usuid'       => $this->ctx['usuario'],
            'ven_sucid'       => $suc['id'],
            'ven_pedid'       => null,
            'ven_timbrado'    => $tim->tim_numero,
            'ven_timb_inicio' => $tim->tim_fechin,
            'ven_timb_venc'   => $tim->tim_fechfn,
            'ven_emp_ruc'     => $this->ctx['empresa']->emp_ruc,
            'ven_emp_razon'   => mb_substr($this->ctx['empresa']->emp_razonsocial, 0, 45),
            'ven_establec'    => $tim->tim_establec,
            'ven_puntoexp'    => $tim->tim_puntexp,
            'ven_numero'      => $numero,
            'ven_fchaem'      => $fecha->toDateString(),
            'ven_razonsocial' => "{$cliente->per_nombr1} {$cliente->per_apell1}",
            'ven_ci'          => $cliente->per_cedula,
            'ven_condicion'   => 1,
            'ven_exenta'      => $tot['exenta'],
            'ven_grav5'       => $tot['grav5'],
            'ven_grav10'      => $tot['grav10'],
            'ven_subtotal'    => $subtotal,
            'ven_total'       => $subtotal,
            'ven_iva5'        => $tot['iva5'],
            'ven_iva10'       => $tot['iva10'],
            'ven_totiva'      => $tot['iva5'] + $tot['iva10'],
            'ven_estado'      => 2,
            'ven_obs'         => self::MARCA,
            'ven_creado'      => $fecha,
        ], 'ven_id');

        DB::table('venta_detalle')->insert(array_map(fn ($l, $i) => [
            'vnd_venid'       => $ventaId,
            'vnd_prdid'       => $l['p']['id'],
            'vnd_nroitem'     => $i + 1,
            'vnd_codigo'      => $l['p']['codigo'] ?? ('PRD-' . $l['p']['id']),
            'vnd_descripcion' => $l['p']['nombre'],
            'vnd_cantidad'    => $l['cant'],
            'vnd_precio'      => $l['p']['precio'],
            'vnd_tasa'        => $l['p']['tasa'],
            'vnd_valorventa'  => $l['total'],
            'vnd_iva'         => $l['iva'],
            'vnd_cosuni'      => round($l['p']['costo'] * mt_rand(95, 105) / 100, 4),
        ], $lineas, array_keys($lineas)));

        $this->facturas++;
        $this->unidades += array_sum(array_column($lineas, 'cant'));
    }

    // =====================================================================
    // Utilidades
    // =====================================================================

    private function primerLunes(): CarbonImmutable
    {
        return CarbonImmutable::today()
            ->startOfWeek(CarbonInterface::MONDAY)
            ->subWeeks(self::SEMANAS - 1);
    }

    /** Algoritmo de Knuth: número aleatorio con distribución de Poisson(λ). */
    private function poisson(float $lambda): int
    {
        if ($lambda <= 0) {
            return 0;
        }

        $limite = exp(-$lambda);
        $k = 0;
        $p = 1.0;
        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $limite);

        return $k - 1;
    }

    /** Día de la semana (0 = lunes) según PESO_DIA, limitado a los días ya transcurridos. */
    private function elegirDia(int $diasMax): int
    {
        $pesos = array_slice(self::PESO_DIA, 0, $diasMax);
        $r = mt_rand(1, array_sum($pesos));
        foreach ($pesos as $dia => $peso) {
            if (($r -= $peso) <= 0) {
                return $dia;
            }
        }

        return 0;
    }

    /** 75 % de las ventas en la sucursal principal; el resto repartido entre las demás. */
    private function elegirSucursal(): array
    {
        $sucursales = $this->ctx['sucursales'];
        if (count($sucursales) === 1 || mt_rand(1, 100) <= 75) {
            return $sucursales[0]; // ordenadas con la principal primero
        }

        return $sucursales[mt_rand(1, count($sucursales) - 1)];
    }

    private function azar(float $min, float $max): float
    {
        return $min + ($max - $min) * mt_rand() / mt_getrandmax();
    }

    private function actualizarTimbrados(): void
    {
        foreach ($this->numeros as $timId => $ultimo) {
            DB::table('timbrado')->where('tim_id', $timId)->update(['tim_nroact' => $ultimo]);
        }
    }

    private function exportarPerfiles(array $perfiles): string
    {
        $ruta  = storage_path('app/demo_perfiles_demanda.csv');
        $filas = ["prd_id;codigo;perfil;lambda_semanal;tendencia"];
        foreach ($perfiles as $p) {
            $filas[] = implode(';', [$p['producto']['id'], $p['producto']['codigo'], $p['perfil'], $p['lambda'], $p['tendencia']]);
        }
        file_put_contents($ruta, implode(PHP_EOL, $filas) . PHP_EOL);

        return $ruta;
    }
} 