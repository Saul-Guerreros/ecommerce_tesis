<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Datos de DEMOSTRACIÓN para el Módulo de Métricas y Reportes.
 *
 * Genera ~14 meses de historia coherente con las reglas del sistema:
 *   - Clientes (persona + cliente + contacto), con altas distribuidas en el tiempo.
 *   - Pedidos con detalle, entrega (envío/retiro), pago y estados realistas:
 *     entregados+pagados (con factura vigente), cancelados y pendientes.
 *   - Facturas de venta (venta_cab/venta_detalle) con costo histórico (vnd_cosuni),
 *     usando las cajas y timbrados reales de cada sucursal.
 *   - Compras confirmadas con cuotas (pagadas, vencidas y por vencer).
 *   - Conversaciones del chatbot: herramientas, consultas sin respuesta y derivadas.
 *
 * NO toca stock ni movimiento_stock (no altera el kardex ni el costo promedio).
 *
 * Todo lo generado queda marcado para poder borrarlo con LimpiarDemoMetricasSeeder:
 *   persona.per_cedula 'DEMO-%' · pedido.ped_numero 'DEMO-%' · venta_cab.ven_obs,
 *   compra_cab.com_obs, timbrado.tim_observacion y chat_conversacion.cnv_ip = 'DEMO-METRICAS'.
 *
 * Las facturas usan un timbrado DEMO propio e inactivo por sucursal: no consumen
 * la numeración de tus timbrados reales.
 *
 * Uso (solo desarrollo):
 *   php artisan db:seed --class=DemoMetricasSeeder
 */
class DemoMetricasSeeder extends Seeder
{
    public const MARCA = 'DEMO-METRICAS';

    private const MESES        = 14;
    private const CLIENTES     = 45;
    private const SEMILLA      = 2026;   // misma semilla = mismos datos en cada corrida

    private const CIUDADES = [
        ['CENTRAL', 'Capiatá'], ['CENTRAL', 'Capiatá'], ['CENTRAL', 'San Lorenzo'],
        ['CENTRAL', 'Luque'], ['CENTRAL', 'Fernando de la Mora'], ['CENTRAL', 'Lambaré'],
        ['CENTRAL', 'Itauguá'], ['CAPITAL', 'Asunción'], ['CAPITAL', 'Asunción'],
        ['ALTO PARANÁ', 'Ciudad del Este'], ['ITAPÚA', 'Encarnación'], ['CORDILLERA', 'Caacupé'],
        ['PARAGUARÍ', 'Paraguarí'], ['GUAIRÁ', 'Villarrica'], ['CAAGUAZÚ', 'Coronel Oviedo'],
    ];

    private const NOMBRES = [
        'Juan', 'María', 'Carlos', 'Ana', 'Luis', 'Sofía', 'Diego', 'Lucía', 'Marcos', 'Camila',
        'José', 'Valeria', 'Pedro', 'Rocío', 'Hugo', 'Natalia', 'Óscar', 'Gabriela', 'Rubén', 'Liz',
    ];

    private const APELLIDOS = [
        'González', 'Benítez', 'Martínez', 'López', 'Giménez', 'Vera', 'Duarte', 'Ramírez',
        'Acosta', 'Cáceres', 'Ortiz', 'Villalba', 'Rojas', 'Báez', 'Fernández', 'Núñez',
    ];

    /** Preguntas repetidas a propósito para alimentar "Preguntas frecuentes". */
    private const PREGUNTAS = [
        ['tienen notebooks lenovo', 'buscar_productos', true],
        ['precio de la notebook samsung', 'buscar_productos', true],
        ['hacen envíos al interior?', null, true],
        ['cuál es el horario de atención', null, true],
        ['tienen monitores de 27 pulgadas', 'buscar_productos', true],
        ['quiero ver el detalle de un producto', 'ver_producto', true],
        ['aceptan pago con tigo money?', null, true],
        ['tienen mouse inalámbrico logitech', 'buscar_productos', true],
        ['agregame la notebook al carrito', 'proponer_agregar_carrito', true],
        ['tienen impresoras 3d', 'buscar_productos', false],
        ['venden placas de video rtx 5090', 'buscar_productos', false],
        ['tienen repuestos para iphone', 'buscar_productos', false],
    ];

    private array $ctx = [];
    private array $usados = [];   // último número usado por timbrado
    private int $nroPedido = 0;
    private int $nroCompra = 0;

    public function run(): void
    {

        if (DB::table('persona')->where('per_cedula', 'like', 'DEMO-%')->exists()) {
            $this->command->warn('Ya existen datos demo. Ejecutá primero: php artisan db:seed --class=LimpiarDemoMetricasSeeder');
            return;
        }

        mt_srand(self::SEMILLA);

        DB::transaction(function () {
            $this->ctx = $this->cargarContexto();
            $clientes = $this->crearClientes();
            [$pedidos, $facturas] = $this->crearPedidosYVentas($clientes);
            $compras = $this->crearCompras();
            $conversaciones = $this->crearConversaciones();
            $this->actualizarTimbrados();

            $this->command->info('Datos demo generados:');
            $this->command->table(['Entidad', 'Cantidad'], [
                ['Clientes', count($clientes)],
                ['Pedidos', $pedidos],
                ['Facturas de venta', $facturas],
                ['Compras', $compras],
                ['Conversaciones del chatbot', $conversaciones],
            ]);
            $this->command->warn('Ejecutá "php artisan cache:clear" para ver los datos en el dashboard.');
        });
    }

    // =====================================================================
    // Contexto: catálogos y configuración real del sistema
    // =====================================================================

    private function cargarContexto(): array
    {
        $usuario = DB::table('usuario')->orderBy('usu_id')->value('usu_id')
            ?? throw new RuntimeException('No hay usuarios: ejecutá primero los seeders base.');

        $id = fn (string $tabla, string $pk, string $col, string $codigo) =>
            DB::table($tabla)->where($col, $codigo)->value($pk)
            ?? throw new RuntimeException("Falta el registro {$codigo} en {$tabla}.");

        $empresa = DB::table('empresa')->first()
            ?? throw new RuntimeException('No hay empresa configurada.');
        $tcoFactura = DB::table('tipo_comprobante')->where('tco_codigo', '001')->value('tco_id')
            ?? throw new RuntimeException('Falta el tipo de comprobante 001 (Factura).');

        // Sucursales con su depósito principal, caja y un timbrado DEMO propio.
        $sucursales = [];
        foreach (DB::table('sucursal')->where('suc_estado', 1)->orderByDesc('suc_princi')->get() as $s) {
            $caja = DB::table('caja')->where('caj_sucid', $s->suc_id)->where('caj_estado', 1)
                ->orderByDesc('caj_tipo')->first();
            $deposito = DB::table('deposito')->where('dpo_sucid', $s->suc_id)
                ->where('dpo_princi', 1)->value('dpo_id');

            if (!$caja || !$deposito) {
                continue;
            }

            // Timbrado propio del demo, INACTIVO: no consume la numeración real
            // y VentaService no puede elegirlo para emitir facturas reales.
            $timId = DB::table('timbrado')->insertGetId([
                'tim_empid'       => $empresa->emp_id,
                'tim_tcoid'       => $tcoFactura,
                'tim_sucid'       => $s->suc_id,
                'tim_numero'      => '99' . str_pad((string) $s->suc_id, 6, '0', STR_PAD_LEFT),
                'tim_establec'    => DB::table('timbrado')->where('tim_sucid', $s->suc_id)->value('tim_establec') ?? '001',
                'tim_puntexp'     => $caja->caj_puntexp,
                'tim_fechin'      => now()->subMonthsNoOverflow(self::MESES)->startOfMonth()->toDateString(),
                'tim_fechfn'      => now()->addYear()->toDateString(),
                'tim_nroini'      => 1,
                'tim_nrofin'      => 9999999,
                'tim_nroact'      => 0,
                'tim_observacion' => self::MARCA,
                'tim_estado'      => 0,
            ], 'tim_id');

            $sucursales[] = [
                'id' => (int) $s->suc_id, 'principal' => (bool) $s->suc_princi,
                'caja' => $caja, 'timbrado' => DB::table('timbrado')->where('tim_id', $timId)->first(),
                'deposito' => (int) $deposito,
            ];
            $this->usados[$timId] = 0;
        }

        if (!$sucursales) {
            throw new RuntimeException('Ninguna sucursal activa tiene caja y depósito principal.');
        }

        $productos = DB::table('producto as pr')
            ->join('stock as st', 'st.sto_prdid', '=', 'pr.prd_id')
            ->leftJoin('tipo_iva as ti', 'ti.tiv_id', '=', 'pr.prd_tivid')
            ->leftJoin('publicacion_web as pw', 'pw.pub_prdid', '=', 'pr.prd_id')
            ->where('pr.prd_estado', 1)
            ->where('pr.prd_pvtmin', '>', 0)
            ->groupBy('pr.prd_id', 'pr.prd_codinv', 'pw.pub_titulo', 'pr.prd_descri', 'pr.prd_pvtmin', 'ti.tiv_tasa')
            ->selectRaw("pr.prd_id, pr.prd_codinv,
                LEFT(COALESCE(pw.pub_titulo, pr.prd_descri), 140) AS nombre,
                pr.prd_pvtmin AS precio, COALESCE(ti.tiv_tasa, 10) AS tasa,
                MAX(st.sto_cosprm) AS costo")
            ->get()
            ->map(function ($p) {
                $precio = (float) $p->precio;
                $tasa   = (float) $p->tasa;
                $costo  = (float) $p->costo > 0 ? (float) $p->costo : round($precio / (1 + $tasa / 100) / 1.3, 4);

                return ['id' => (int) $p->prd_id, 'codigo' => $p->prd_codinv, 'nombre' => $p->nombre,
                        'precio' => $precio, 'tasa' => in_array($tasa, [0.0, 5.0, 10.0], true) ? $tasa : 10.0, 'costo' => $costo];
            })
            ->values()
            ->all();

        if (count($productos) < 3) {
            throw new RuntimeException('Se necesitan al menos 3 productos activos con precio minorista y stock.');
        }

        return [
            'usuario'     => (int) $usuario,
            'empresa'     => $empresa,
            'sucursales'  => $sucursales,
            'productos'   => $productos,
            'tpd_ci'      => $id('tipo_documento', 'tpd_id', 'tpd_codigo', 'CI'),
            'tpc'         => DB::table('tipo_cliente')->orderBy('tpc_id')->value('tpc_id')
                             ?? throw new RuntimeException('No hay tipos de cliente.'),
            'est'         => DB::table('estado_pedido')->pluck('est_id', 'est_codigo')->all(),
            'epa'         => DB::table('estado_pago')->pluck('epa_id', 'epa_codigo')->all(),
            'ten'         => DB::table('tipo_entrega')->pluck('ten_id', 'ten_codigo')->all(),
            'mpa'         => DB::table('metodo_pago')->pluck('mpa_id', 'mpa_codigo')->all(),
            'tco_factura' => $tcoFactura,
            'proveedores' => DB::table('proveedor')->where('pro_estado', 1)->get(['pro_id', 'pro_ruc', 'pro_razon'])->all(),
        ];
    }

    // =====================================================================
    // Clientes
    // =====================================================================

    private function crearClientes(): array
    {
        $clientes = [];
        $inicio   = now()->subMonthsNoOverflow(self::MESES - 1)->startOfMonth();

        for ($i = 1; $i <= self::CLIENTES; $i++) {
            $nombre   = $this->elegir(self::NOMBRES);
            $apellido = $this->elegir(self::APELLIDOS);
            $cedula   = 'DEMO-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);

            // Altas repartidas en el período, con más registros en los primeros meses.
            $alta = $inicio->copy()->addDays((int) (pow(mt_rand(0, 1000) / 1000, 1.6) * $inicio->diffInDays(now())));

            $personaId = DB::table('persona')->insertGetId([
                'per_tpdid'  => $this->ctx['tpd_ci'],
                'per_nombr1' => $nombre,
                'per_apell1' => $apellido,
                'per_cedula' => $cedula,
                'per_estado' => 1,
                'per_cread'  => $alta,
            ], 'per_id');

            $clienteId = DB::table('cliente')->insertGetId([
                'cli_perid'  => $personaId,
                'cli_tpcid'  => $this->ctx['tpc'],
                'cli_estado' => 1,
                'cli_creado' => $alta,
            ], 'cli_id');

            $slug = Str::slug("{$nombre}.{$apellido}.{$i}", '.');
            DB::table('contacto')->insert([
                ['con_perid' => $personaId, 'con_tipo' => 'EMAIL', 'con_valor' => "{$slug}@demo.com.py", 'con_estado' => 1],
                ['con_perid' => $personaId, 'con_tipo' => 'CELULAR', 'con_valor' => '09' . mt_rand(71, 99) . mt_rand(100000, 999999), 'con_estado' => 1],
            ]);

            $clientes[] = [
                'id' => $clienteId, 'nombre' => $nombre, 'apellido' => $apellido, 'cedula' => $cedula, 'alta' => $alta,
                // Perfiles: 10 frecuentes, 30 ocasionales, 5 que dejan de comprar (inactivos).
                'perfil' => $i <= 10 ? 'frecuente' : ($i > self::CLIENTES - 5 ? 'abandona' : 'ocasional'),
            ];
        }

        return $clientes;
    }

    // =====================================================================
    // Pedidos y facturas
    // =====================================================================

    private function crearPedidosYVentas(array $clientes): array
    {
        $pedidos = 0;
        $facturas = 0;

        for ($m = self::MESES - 1; $m >= 0; $m--) {
            $mes  = now()->subMonthsNoOverflow($m)->startOfMonth();
            $fin  = $m === 0 ? now() : $mes->copy()->endOfMonth();

            // Tendencia creciente + estacionalidad (noviembre y diciembre más fuertes).
            $base = 18 + (self::MESES - 1 - $m) * 1.6;
            $base *= match ((int) $mes->month) { 12 => 1.6, 11 => 1.25, 1, 2 => 0.85, default => 1.0 };
            $cantidad = (int) round($base * mt_rand(85, 115) / 100 * ($m === 0 ? now()->day / now()->daysInMonth : 1));

            for ($k = 0; $k < $cantidad; $k++) {
                $fecha = $mes->copy()->addSeconds(mt_rand(0, max(1, $mes->diffInSeconds($fin))));
                $fecha->setTime(mt_rand(8, 21), mt_rand(0, 59), mt_rand(0, 59));
                if ($fecha->gt(now())) {
                    $fecha = now()->subMinutes(mt_rand(5, 600));
                }

                $cliente = $this->elegirCliente($clientes, $fecha, $m);
                if (!$cliente) {
                    continue;
                }

                $facturas += $this->crearPedido($cliente, $fecha) ? 1 : 0;
                $pedidos++;
            }
        }

        return [$pedidos, $facturas];
    }

    private function elegirCliente(array $clientes, Carbon $fecha, int $mesesAtras): ?array
    {
        $candidatos = array_values(array_filter($clientes, function ($c) use ($fecha, $mesesAtras) {
            if ($c['alta']->gt($fecha)) {
                return false;
            }
            // Los que "abandonan" solo compran en los primeros meses: quedan inactivos.
            return $c['perfil'] !== 'abandona' || $mesesAtras >= self::MESES - 5;
        }));

        if (!$candidatos) {
            return null;
        }

        // Los frecuentes concentran más compras (alimenta "Top clientes").
        $frecuentes = array_values(array_filter($candidatos, fn ($c) => $c['perfil'] === 'frecuente'));
        return $frecuentes && mt_rand(1, 100) <= 45 ? $this->elegir($frecuentes) : $this->elegir($candidatos);
    }

    /** Crea un pedido completo. Devuelve true si se emitió factura. */
    private function crearPedido(array $cliente, Carbon $fecha): bool
    {
        $c   = $this->ctx;
        $suc = $this->elegir($c['sucursales']);
        $esEnvio = mt_rand(1, 100) <= 60;

        // Método de pago
        $r = mt_rand(1, 100);
        $metodo = $r <= 45 ? 'TRANSFERENCIA' : ($r <= 65 ? 'BILLETERA_MOVIL' : 'CONTRAENTREGA');
        $mpaId  = $c['mpa'][$metodo] ?? reset($c['mpa']);

        // Resultado del pedido según su antigüedad
        $dias = $fecha->diffInDays(now());
        $r = mt_rand(1, 100);
        if ($dias <= 3 && $r <= 70) {
            $estado = $r <= 35 ? 'PENDIENTE_PEDIDO' : 'EN_PREPARACION';
            $pago   = $metodo === 'CONTRAENTREGA' ? 'PENDIENTE_PAGO' : ($r <= 35 ? 'PAGO_VERIFICAR' : 'CONFIRMADO');
        } elseif ($r <= 9) {
            $estado = 'CANCELADO';
            $pago   = $metodo === 'CONTRAENTREGA' ? 'PENDIENTE_PAGO' : 'RECHAZADO';
        } else {
            $estado = 'ENTREGADO';
            $pago   = 'CONFIRMADO';
        }
        $facturar = $pago === 'CONFIRMADO' || $estado === 'ENTREGADO';

        // Líneas (1 a 3 productos distintos; precio con IVA incluido)
        $lineas = [];
        foreach ($this->elegirVarios($c['productos'], mt_rand(1, 100) <= 60 ? 1 : mt_rand(2, 3)) as $p) {
            $cant  = mt_rand(1, 100) <= 85 ? 1 : 2;
            $total = round($p['precio'] * $cant, 2);
            $iva   = $p['tasa'] > 0 ? round($total * $p['tasa'] / (100 + $p['tasa']), 2) : 0.0;
            $lineas[] = ['p' => $p, 'cant' => $cant, 'total' => $total, 'iva' => $iva];
        }
        $total = array_sum(array_column($lineas, 'total'));
        $iva   = array_sum(array_column($lineas, 'iva'));

        $this->nroPedido++;
        $pedidoId = DB::table('pedido')->insertGetId([
            'ped_numero' => 'DEMO-' . str_pad((string) $this->nroPedido, 6, '0', STR_PAD_LEFT),
            'ped_cliid'  => $cliente['id'],
            'ped_subtot' => $total - $iva,
            'ped_impues' => $iva,
            'ped_cosenv' => 0,
            'ped_total'  => $total,
            'ped_tenid'  => $c['ten'][$esEnvio ? 'ENVIO' : 'RETIRO'],
            'ped_sucid'  => $esEnvio ? null : $suc['id'],
            'ped_mpaid'  => $mpaId,
            'ped_estid'  => $c['est'][$estado],
            'ped_epaid'  => $c['epa'][$pago],
            'ped_obsint' => self::MARCA,
            'ped_creado' => $fecha,
            'ped_actual' => $fecha,
        ], 'ped_id');

        foreach ($lineas as $l) {
            DB::table('detalle_pedido')->insert([
                'dpd_pedid'  => $pedidoId,
                'dpd_prdid'  => $l['p']['id'],
                'dpd_descri' => $l['p']['nombre'],
                'dpd_cantid' => $l['cant'],
                'dpd_precio' => $l['p']['precio'],
                'dpd_subtot' => $l['total'],
                'dpd_total'  => $l['total'],
                'dpd_iva'    => $l['iva'],
            ]);
        }

        [$departamento, $ciudad] = $this->elegir(self::CIUDADES);
        DB::table('pedido_entrega')->insert([
            'pen_pedid'   => $pedidoId,
            'pen_tenid'   => $c['ten'][$esEnvio ? 'ENVIO' : 'RETIRO'],
            'pen_sucid'   => $esEnvio ? null : $suc['id'],
            'pen_nrodoc'  => $cliente['cedula'],
            'pen_nombre'  => $cliente['nombre'],
            'pen_apelli'  => $cliente['apellido'],
            'pen_departa' => $esEnvio ? $departamento : null,
            'pen_ciudad'  => $esEnvio ? $ciudad : null,
            'pen_calle'   => $esEnvio ? 'Calle demo ' . mt_rand(1, 999) : null,
        ]);

        DB::table('pedido_pago')->insert([
            'ppa_pedid'  => $pedidoId,
            'ppa_orden'  => 1,
            'ppa_mpaid'  => $mpaId,
            'ppa_monto'  => $total,
            'ppa_epaid'  => $c['epa'][$pago],
            'ppa_creado' => $fecha,
        ]);

        if (!$facturar) {
            return false;
        }

        // Los envíos se facturan en la sucursal principal (igual que VentaService).
        $emisora = $esEnvio ? $this->sucursalPrincipal() : $suc;
        $this->crearFactura($pedidoId, $cliente, $emisora, $lineas, $fecha->copy()->addDays(mt_rand(0, 2)));

        return true;
    }

    private function crearFactura(int $pedidoId, array $cliente, array $suc, array $lineas, Carbon $fecha): void
    {
        if ($fecha->gt(now())) {
            $fecha = now();
        }

        $tim = $suc['timbrado'];
        $numero = ++$this->usados[$tim->tim_id];
        if ($numero > (int) $tim->tim_nrofin) {
            throw new RuntimeException("El timbrado {$tim->tim_numero} no tiene numeración suficiente (fin {$tim->tim_nrofin}).");
        }

        $tot = ['exenta' => 0, 'grav5' => 0, 'grav10' => 0, 'iva5' => 0, 'iva10' => 0];
        foreach ($lineas as $l) {
            match ((int) $l['p']['tasa']) {
                5       => [$tot['grav5'] += $l['total'], $tot['iva5'] += $l['iva']],
                10      => [$tot['grav10'] += $l['total'], $tot['iva10'] += $l['iva']],
                default => $tot['exenta'] += $l['total'],
            };
        }
        $subtotal = $tot['exenta'] + $tot['grav5'] + $tot['grav10'];

        $ventaId = DB::table('venta_cab')->insertGetId([
            'ven_timid'       => $tim->tim_id,
            'ven_cajid'       => $suc['caja']->caj_id,
            'ven_cliid'       => $cliente['id'],
            'ven_usuid'       => $this->ctx['usuario'],
            'ven_sucid'       => $suc['id'],
            'ven_pedid'       => $pedidoId,
            'ven_timbrado'    => $tim->tim_numero,
            'ven_timb_inicio' => $tim->tim_fechin,
            'ven_timb_venc'   => $tim->tim_fechfn,
            'ven_emp_ruc'     => $this->ctx['empresa']->emp_ruc,
            'ven_emp_razon'   => mb_substr($this->ctx['empresa']->emp_razonsocial, 0, 45),
            'ven_establec'    => $tim->tim_establec,
            'ven_puntoexp'    => $tim->tim_puntexp,
            'ven_numero'      => $numero,
            'ven_fchaem'      => $fecha->toDateString(),
            'ven_razonsocial' => "{$cliente['nombre']} {$cliente['apellido']}",
            'ven_ci'          => $cliente['cedula'],
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

        foreach ($lineas as $i => $l) {
            DB::table('venta_detalle')->insert([
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
                // Costo histórico con leve variación (simula compras a distinto precio).
                'vnd_cosuni'      => round($l['p']['costo'] * mt_rand(95, 105) / 100, 4),
            ]);
        }
    }

    // =====================================================================
    // Compras y cuentas por pagar
    // =====================================================================

    private function crearCompras(): int
    {
        $c = $this->ctx;
        if (!$c['proveedores'] || !$c['tco_factura']) {
            $this->command->warn('Sin proveedores activos o sin tipo de comprobante 001: se omiten las compras.');
            return 0;
        }

        $total = 0;
        for ($m = self::MESES - 1; $m >= 0; $m--) {
            $mes = now()->subMonthsNoOverflow($m)->startOfMonth();
            $tope = $m === 0 ? now()->day : $mes->daysInMonth;

            for ($k = 0, $n = mt_rand(3, 6); $k < $n; $k++) {
                $prov  = $this->elegir($c['proveedores']);
                $suc   = $this->elegir($c['sucursales']);
                $fecha = $mes->copy()->addDays(mt_rand(0, max(0, $tope - 1)));
                $monto = (float) (mt_rand(20, 250) * 100000);
                $grav  = round($monto / 1.1, 2);
                $credito = mt_rand(1, 100) <= 55;
                $cuotas  = $credito ? mt_rand(1, 3) : null;

                $this->nroCompra++;
                $compraId = DB::table('compra_cab')->insertGetId([
                    'com_proid'  => $prov->pro_id,
                    'com_depid'  => $suc['deposito'],
                    'com_sucid'  => $suc['id'],
                    'com_usuid'  => $c['usuario'],
                    'com_tcoid'  => $c['tco_factura'],
                    'com_proruc' => $prov->pro_ruc,
                    'com_proraz' => $prov->pro_razon,
                    'com_timbra' => '90000001',
                    'com_nrodoc' => '900-001-' . str_pad((string) $this->nroCompra, 7, '0', STR_PAD_LEFT),
                    'com_fchaem' => $fecha->toDateString(),
                    'com_condic' => $credito ? 1 : 0,
                    'com_plzdia' => $credito ? 30 : null,
                    'com_cuotas' => $cuotas,
                    'com_interv' => $credito ? 30 : null,
                    'com_fchvto' => $credito ? $fecha->copy()->addDays(30 * $cuotas)->toDateString() : null,
                    'com_grav10' => $grav,
                    'com_iva10'  => $monto - $grav,
                    'com_total'  => $monto,
                    'com_estfac' => 1,
                    'com_obs'    => self::MARCA,
                    'com_creado' => $fecha,
                ], 'com_id');

                if ($credito) {
                    $importe = round($monto / $cuotas, 2);
                    for ($q = 1; $q <= $cuotas; $q++) {
                        $venc = $fecha->copy()->addDays(30 * $q);
                        // Cuotas viejas casi siempre pagadas; algunas quedan vencidas.
                        $pagada = $venc->lt(now()) && mt_rand(1, 100) <= 80;

                        DB::table('cuota_compra')->insert([
                            'cuo_comid'   => $compraId,
                            'cuo_nrocuo'  => $q,
                            'cuo_venci'   => $venc->toDateString(),
                            'cuo_importe' => $q === $cuotas ? $monto - $importe * ($cuotas - 1) : $importe,
                            'cuo_estado'  => $pagada ? 1 : 0,
                            'cuo_fecpago' => $pagada ? $venc->copy()->subDays(mt_rand(0, 5))->toDateString() : null,
                            'cuo_pagusu'  => $pagada ? $c['usuario'] : null,
                        ]);
                    }
                }
                $total++;
            }
        }

        return $total;
    }

    // =====================================================================
    // Chatbot
    // =====================================================================

    private function crearConversaciones(): int
    {
        $total = 0;

        for ($m = self::MESES - 1; $m >= 0; $m--) {
            $mes  = now()->subMonthsNoOverflow($m)->startOfMonth();
            $tope = $m === 0 ? now()->day : $mes->daysInMonth;

            for ($k = 0, $n = mt_rand(25, 60); $k < $n; $k++) {
                $inicio = $mes->copy()->addDays(mt_rand(0, max(0, $tope - 1)))
                    ->setTime(mt_rand(8, 22), mt_rand(0, 59));
                if ($inicio->gt(now())) {
                    $inicio = now()->subMinutes(mt_rand(10, 300));
                }

                $derivada = mt_rand(1, 100) <= 8;
                $momento  = $inicio->copy();

                $cnvId = DB::table('chat_conversacion')->insertGetId([
                    'cnv_token'        => (string) Str::uuid(),
                    'cnv_estado'       => $derivada ? 'derivada' : 'cerrada',
                    'cnv_motivo_deriv' => $derivada ? $this->elegir([
                        'El cliente pidió hablar con un vendedor',
                        'Consulta sobre garantía de un equipo',
                        'Reclamo por demora en la entrega',
                    ]) : null,
                    'cnv_ip'           => self::MARCA,
                    'cnv_creado'       => $inicio,
                    'cnv_ultimo_msj'   => $inicio,
                ], 'cnv_id');

                for ($t = 0, $turnos = mt_rand(1, 3); $t < $turnos; $t++) {
                    [$texto, $herramienta, $resuelve] = $this->elegir(self::PREGUNTAS);
                    $this->mensaje($cnvId, 'usuario', $texto, $momento->addSeconds(mt_rand(10, 90)));

                    if ($herramienta) {
                        $this->mensaje($cnvId, 'herramienta', null, $momento->addSeconds(1), [
                            'cms_herramienta' => $herramienta,
                            'cms_datos'       => json_encode(['args' => ['consulta' => $texto], 'resultados' => $resuelve ? mt_rand(1, 6) : 0]),
                        ]);
                    }

                    $this->mensaje($cnvId, 'asistente',
                        $resuelve ? 'Te muestro las opciones disponibles en nuestra tienda.' : 'No encontré productos que coincidan con tu búsqueda.',
                        $momento->addSeconds(mt_rand(2, 6)), [
                            'cms_modelo'      => 'gemini-demo',
                            'cms_tokens_ent'  => mt_rand(600, 1800),
                            'cms_tokens_sal'  => mt_rand(60, 300),
                            'cms_latencia_ms' => mt_rand(700, 4200),
                            'cms_sinres'      => !$resuelve,
                        ]);
                }

                if ($derivada) {
                    $this->mensaje($cnvId, 'usuario', 'quiero hablar con una persona', $momento->addSeconds(30));
                    $this->mensaje($cnvId, 'herramienta', null, $momento->addSeconds(1), [
                        'cms_herramienta' => 'solicitar_atencion_humana',
                        'cms_datos'       => json_encode(['motivo' => 'solicitud del cliente']),
                    ]);
                    $this->mensaje($cnvId, 'asistente', 'Te derivo con un asesor de la tienda.', $momento->addSeconds(3), [
                        'cms_modelo' => 'gemini-demo', 'cms_tokens_ent' => 900, 'cms_tokens_sal' => 40, 'cms_latencia_ms' => 1200,
                    ]);
                }

                DB::table('chat_conversacion')->where('cnv_id', $cnvId)->update(['cnv_ultimo_msj' => $momento]);
                $total++;
            }
        }

        return $total;
    }

    private function mensaje(int $cnvId, string $rol, ?string $contenido, Carbon $fecha, array $extra = []): void
    {
        DB::table('chat_mensaje')->insert([
            'cnv_id'        => $cnvId,
            'cms_rol'       => $rol,
            'cms_contenido' => $contenido,
            'cms_creado'    => $fecha->copy(),
            ...$extra,
        ]);
    }

    // =====================================================================
    // Auxiliares
    // =====================================================================

    /** Deja tim_nroact del timbrado demo en el último número usado. */
    private function actualizarTimbrados(): void
    {
        foreach ($this->usados as $timId => $ultimo) {
            DB::table('timbrado')->where('tim_id', $timId)->where('tim_nroact', '<', $ultimo)
                ->update(['tim_nroact' => $ultimo]);
        }
    }

    private function sucursalPrincipal(): array
    {
        foreach ($this->ctx['sucursales'] as $s) {
            if ($s['principal']) {
                return $s;
            }
        }
        return $this->ctx['sucursales'][0];
    }

    private function elegir(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    private function elegirVarios(array $items, int $cantidad): array
    {
        $claves = array_rand($items, min($cantidad, count($items)));
        return array_map(fn ($k) => $items[$k], (array) $claves);
    }
}
