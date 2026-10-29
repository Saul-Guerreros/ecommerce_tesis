<?php

namespace Database\Seeders;

use App\Models\PublicacionWeb;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos de DEMOSTRACIÓN para el Análisis de comportamiento de clientes:
 * búsquedas (con y sin resultado) y vistas de producto de visitantes anónimos.
 * Complementa a DemoMetricasSeeder (ventas, clientes y pedidos demo).
 *
 * Marca: los visitantes demo usan UUID que empiezan con VISITA_DEMO,
 * así LimpiarDemoComportamientoSeeder los borra sin tocar datos reales.
 *
 *   php artisan db:seed --class=DemoComportamientoSeeder
 */
class DemoComportamientoSeeder extends Seeder
{
    public const VISITA_DEMO = '00000000-0000-4000-8000-';

    private const MESES = 14;
    private const VISITANTES = 150;
    private const BUSQUEDAS = 1400;
    private const VISTAS_DIRECTAS = 900;
    private const SEMILLA = 2026;

    /** Demanda que la tienda no cubre: deben aparecer en "sin resultados". */
    private const SIN_STOCK = [
        'monitor 27 pulgadas', 'silla gamer', 'impresora 3d', 'playstation 5',
        'tablet samsung', 'ups 1500va', 'proyector', 'macbook air',
    ];

    /** Horas con más tráfico: noche y mediodía (pesos relativos). */
    private const PESO_HORA = [8 => 2, 9 => 3, 10 => 3, 11 => 4, 12 => 6, 13 => 5, 14 => 3, 15 => 3,
        16 => 3, 17 => 4, 18 => 5, 19 => 7, 20 => 9, 21 => 8, 22 => 5, 23 => 2];

    public function run(): void
    {
        mt_srand(self::SEMILLA);
        $this->call(LimpiarDemoComportamientoSeeder::class);

        $productos = PublicacionWeb::publicadas()
            ->whereNotNull('pub_prdid')
            ->get(['pub_prdid', 'pub_titulo'])
            ->map(fn ($p) => ['id' => (int) $p->pub_prdid, 'termino' => $this->terminoDe((string) $p->pub_titulo)])
            ->filter(fn ($p) => $p['termino'] !== '')
            ->values();

        if ($productos->isEmpty()) {
            $this->command?->warn('No hay productos publicados: corré primero la carga del catálogo.');
            return;
        }

        $visitantes = array_map(fn ($i) => self::VISITA_DEMO . str_pad(dechex($i), 12, '0', STR_PAD_LEFT), range(1, self::VISITANTES));
        // Unos pocos productos concentran el interés (como en una tienda real).
        $populares = $productos->shuffle(self::SEMILLA)->take(max(3, (int) ($productos->count() * 0.3)))->values();

        $vistas = [];
        for ($i = 0; $i < self::BUSQUEDAS; $i++) {
            $visita = $visitantes[mt_rand(0, self::VISITANTES - 1)];
            $fecha = $this->fechaAleatoria();
            $sinStock = mt_rand(1, 100) <= 12;
            $producto = mt_rand(1, 100) <= 70 ? $this->alAzar($populares) : $this->alAzar($productos);
            $termino = $sinStock ? self::SIN_STOCK[array_rand(self::SIN_STOCK)] : $producto['termino'];
            $total = $sinStock ? 0 : mt_rand(1, 12);

            $busId = DB::table('busqueda')->insertGetId([
                'bus_termino'  => $termino,
                'bus_termnorm' => Str::of($termino)->ascii()->lower()->squish()->value(),
                'bus_total'    => $total,
                'bus_modo'     => $sinStock ? 'sin_resultados' : (mt_rand(1, 100) <= 20 ? 'ia' : 'todos'),
                'bus_origen'   => mt_rand(1, 100) <= 15 ? 'CHATBOT' : 'TIENDA',
                'bus_visita'   => $visita,
                'bus_creado'   => $fecha,
            ], 'bus_id');

            // 60 % de las búsquedas con resultado terminan en un producto abierto.
            if (! $sinStock && mt_rand(1, 100) <= 60) {
                $vistas[] = [
                    'vpr_prdid'  => $producto['id'],
                    'vpr_busid'  => $busId,
                    'vpr_visita' => $visita,
                    'vpr_creado' => $fecha->copy()->addSeconds(mt_rand(5, 120)),
                ];
            }
        }

        for ($i = 0; $i < self::VISTAS_DIRECTAS; $i++) {
            $vistas[] = [
                'vpr_prdid'  => (mt_rand(1, 100) <= 60 ? $this->alAzar($populares) : $this->alAzar($productos))['id'],
                'vpr_busid'  => null,
                'vpr_visita' => $visitantes[mt_rand(0, self::VISITANTES - 1)],
                'vpr_creado' => $this->fechaAleatoria(),
            ];
        }

        foreach (array_chunk($vistas, 500) as $lote) {
            DB::table('vista_producto')->insert($lote);
        }

        $this->command?->info(self::BUSQUEDAS . ' búsquedas y ' . count($vistas) . ' vistas demo creadas.');
    }

    /** Elemento al azar con mt_rand: misma semilla = mismos datos. */
    private function alAzar($coleccion): array
    {
        return $coleccion[mt_rand(0, $coleccion->count() - 1)];
    }

    /** "Notebook Lenovo IdeaPad 3 15" → "notebook lenovo" (como escribe un cliente). */
    private function terminoDe(string $titulo): string
    {
        $palabras = array_slice(preg_split('/\s+/', Str::of($titulo)->lower()->squish()->value()) ?: [], 0, 2);

        return trim(implode(' ', $palabras));
    }

    private function fechaAleatoria(): Carbon
    {
        $dia = now()->subDays(mt_rand(0, self::MESES * 30));
        $hora = $this->horaPonderada();

        return $dia->setTime($hora, mt_rand(0, 59), mt_rand(0, 59));
    }

    private function horaPonderada(): int
    {
        $tirada = mt_rand(1, array_sum(self::PESO_HORA));

        foreach (self::PESO_HORA as $hora => $peso) {
            if (($tirada -= $peso) <= 0) {
                return $hora;
            }
        }

        return 20;
    }
}