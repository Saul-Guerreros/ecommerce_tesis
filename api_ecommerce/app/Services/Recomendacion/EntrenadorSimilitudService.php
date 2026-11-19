<?php

namespace App\Services\Recomendacion;

use Carbon\CarbonImmutable;
use App\Models\Recomendacion\ProductoSimilitud;
use App\Models\Venta\VentaCab;
use Illuminate\Support\Facades\DB;

/**
 * Entrena el modelo de Filtrado Colaborativo Ítem-Ítem (feedback implícito).
 *
 *  1. Matriz usuario × producto: compra = peso_compra, vista = peso_vista.
 *     "Usuario" = cliente identificado (c{id}) o visitante anónimo (v{uuid}).
 *  2. Similitud coseno entre cada par de productos.
 *  3. Se guardan los N vecinos más parecidos de cada producto.
 *
 * $hasta es límite EXCLUSIVO: permite entrenar "como si fuera" otra fecha
 * (lo usa recomendaciones:evaluar para no mirar el futuro).
 */
class EntrenadorSimilitudService
{
    /** @return array<string, array<int, float>> usuario => [prd_id => peso] */
    public function interacciones(CarbonImmutable $hasta): array
    {
        $cfg = config('recomendacion');
        $matriz = [];

        // 1) Historial de compras: ventas facturadas (online y presenciales).
        $compras = DB::table('venta_detalle as vd')
            ->join('venta_cab as vc', 'vc.ven_id', '=', 'vd.vnd_venid')
            ->where('vc.ven_estado', '<>', VentaCab::ESTADO_ANULADA)
            ->whereNotNull('vd.vnd_prdid')
            ->where('vc.ven_fchaem', '>=', $hasta->subDays($cfg['dias_compras'])->toDateString())
            ->where('vc.ven_fchaem', '<', $hasta->toDateString())
            ->select('vc.ven_cliid as cli', 'vd.vnd_prdid as prd')
            ->distinct()
            ->cursor();

        foreach ($compras as $f) {
            $matriz["c{$f->cli}"][(int) $f->prd] = (float) $cfg['peso_compra'];
        }

        // 2) Productos consultados: la vista de un cliente logueado se une a sus compras.
        $vistas = DB::table('vista_producto as vp')
            ->leftJoin('usuario as u', 'u.usu_id', '=', 'vp.vpr_usuid')
            ->leftJoin('cliente as cl', 'cl.cli_perid', '=', 'u.usu_perid')
            ->where('vp.vpr_creado', '>=', $hasta->subDays($cfg['dias_vistas']))
            ->where('vp.vpr_creado', '<', $hasta)
            ->where(fn ($q) => $q->whereNotNull('cl.cli_id')->orWhereNotNull('vp.vpr_visita'))
            ->select('cl.cli_id as cli', 'vp.vpr_visita as vis', 'vp.vpr_prdid as prd')
            ->distinct()
            ->cursor();

        foreach ($vistas as $f) {
            $clave = $f->cli ? "c{$f->cli}" : "v{$f->vis}";
            $prd = (int) $f->prd;
            // max(): si ya lo compró, la compra (peso mayor) prevalece.
            $matriz[$clave][$prd] = max($matriz[$clave][$prd] ?? 0.0, (float) $cfg['peso_vista']);
        }

        return $matriz;
    }

    /**
     * sim(a,b) = Σu w(u,a)·w(u,b) / (‖a‖·‖b‖)
     *
     * @param  array<string, array<int, float>> $matriz
     * @return array<int, array<int, array{score: float, soporte: int}>>
     */
    public function similitudes(array $matriz): array
    {
        $cfg = config('recomendacion');
        $punto = [];
        $soporte = [];
        $norma = [];

        foreach ($matriz as $items) {
            // Tope por usuario: evita que un "bot" o un usuario muy activo domine (y el costo O(n²)).
            arsort($items);
            $items = array_slice($items, 0, $cfg['max_items_usuario'], true);

            foreach ($items as $prd => $peso) {
                $norma[$prd] = ($norma[$prd] ?? 0.0) + $peso ** 2;
            }

            $ids = array_keys($items);
            $n = count($ids);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    [$a, $b] = $ids[$i] < $ids[$j] ? [$ids[$i], $ids[$j]] : [$ids[$j], $ids[$i]];
                    $punto[$a][$b] = ($punto[$a][$b] ?? 0.0) + $items[$a] * $items[$b];
                    $soporte[$a][$b] = ($soporte[$a][$b] ?? 0) + 1;
                }
            }
        }

        $vecinos = [];
        foreach ($punto as $a => $fila) {
            foreach ($fila as $b => $valor) {
                $sop = $soporte[$a][$b];
                if ($sop < $cfg['soporte_minimo']) {
                    continue; // coincidencia de un solo usuario = ruido
                }
                $score = $valor / sqrt($norma[$a] * $norma[$b]);
                $vecinos[$a][$b] = ['score' => $score, 'soporte' => $sop];
                $vecinos[$b][$a] = ['score' => $score, 'soporte' => $sop];
            }
        }

        foreach ($vecinos as $prd => $lista) {
            uasort($lista, fn ($x, $y) => $y['score'] <=> $x['score']);
            $vecinos[$prd] = array_slice($lista, 0, $cfg['vecinos'], true);
        }

        return $vecinos;
    }

    /** Reemplaza el modelo vigente de forma atómica. @return int filas guardadas */
    public function guardar(array $vecinos): int
    {
        $ahora = now();
        $filas = [];

        foreach ($vecinos as $prd => $lista) {
            foreach ($lista as $sim => $d) {
                $filas[] = [
                    'psi_prdid'     => $prd,
                    'psi_simid'     => $sim,
                    'psi_score'     => round($d['score'], 6),
                    'psi_soporte'   => $d['soporte'],
                    'psi_calculado' => $ahora,
                ];
            }
        }

        DB::transaction(function () use ($filas) {
            ProductoSimilitud::query()->delete();
            foreach (array_chunk($filas, 1000) as $lote) {
                ProductoSimilitud::insert($lote);
            }
        });

        return count($filas);
    }
}