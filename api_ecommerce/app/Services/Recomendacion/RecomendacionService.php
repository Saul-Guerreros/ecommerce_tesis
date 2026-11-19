<?php

namespace App\Services\Recomendacion;

use App\Models\PublicacionWeb;
use App\Models\Recomendacion\ProductoSimilitud;
use App\Models\Venta\VentaCab;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Motor de recomendación híbrido:
 *   score(p) = (1-α)·CF(p) + α·Tendencia(p)
 *
 *  - CF: Σ peso(i)·sim(i,p) sobre el perfil del visitante (compras + vistas
 *    con decaimiento temporal) y la tabla producto_similitud.
 *  - Tendencia: clientes distintos que compraron + vistas recientes.
 *  - Arranque en frío (sin historial): solo tendencias.
 *
 * Capa de servicio pura: no conoce Request ni formato HTTP. Devuelve ids
 * rankeados + estrategia; el controlador los serializa.
 */
class RecomendacionService
{
    public const CACHE_TENDENCIAS = 'recomendacion:tendencias';

    // ------------------------------------------------------------------
    // Casos de uso (los consume RecomendacionController)
    // ------------------------------------------------------------------

    /**
     * "Recomendado para vos" (Home).
     * @return array{estrategia: string, ids: int[]}
     */
    public function paraVisitante(?int $cliId, ?string $visita): array
    {
        $perfil = $this->perfil($cliId, $visita);
        $tendencias = $this->tendencias();

        if (empty($perfil['pesos'])) {
            return $this->resultado('tendencias', array_keys($tendencias));
        }

        $vecinos = $this->vecinosDe(array_keys($perfil['pesos']));
        $ranking = $this->rankear($perfil, $vecinos, $tendencias);

        return $this->resultado('personalizado', array_keys($ranking));
    }

    /**
     * "Clientes que vieron/compraron esto también…" (Detalle de producto).
     * @return array{estrategia: string, ids: int[]}
     */
    public function similaresA(string $slug, int $limite): array
    {
        $pub = PublicacionWeb::publicadas()->where('pub_slug', $slug)->firstOrFail(['pub_prdid', 'pub_catid']);
        $prd = (int) $pub->pub_prdid;

        $ids = array_keys($this->vecinosDe([$prd])[$prd] ?? []);
        $estrategia = empty($ids) ? 'categoria' : 'similares';

        // Arranque en frío del producto: se completa con su misma categoría,
        // primero lo que está en tendencia y después lo más nuevo.
        if (count($ids) < $limite) {
            $categoria = PublicacionWeb::publicadas()
                ->where('pub_catid', $pub->pub_catid)
                ->orderByDesc('pub_creado')
                ->limit(50)
                ->pluck('pub_prdid')
                ->map(fn ($v) => (int) $v)
                ->all();

            $enTendencia = array_values(array_intersect(array_keys($this->tendencias()), $categoria));
            $ids = array_values(array_unique(array_merge($ids, $enTendencia, $categoria)));
        }

        return $this->resultado($estrategia, array_values(array_diff($ids, [$prd])));
    }

    /** "Tendencias de la tienda". @return array{estrategia: string, ids: int[]} */
    public function tendenciasPublicas(): array
    {
        return $this->resultado('tendencias', array_keys($this->tendencias()));
    }

    // ------------------------------------------------------------------
    // Núcleo del algoritmo (público para poder evaluarlo offline)
    // ------------------------------------------------------------------

    /**
     * Perfil de intereses con decaimiento exponencial:
     * peso = base · 2^(-días / vida_media). Una compra de hace 30 días vale la mitad.
     *
     * @return array{pesos: array<int, float>, comprados: array<int, true>}
     */
    public function perfil(?int $cliId, ?string $visita, ?CarbonImmutable $hasta = null): array
    {
        $cfg = config('recomendacion');
        $hasta ??= CarbonImmutable::tomorrow();
        $pesos = [];
        $comprados = [];

        if ($cliId) {
            $compras = DB::table('venta_detalle as vd')
                ->join('venta_cab as vc', 'vc.ven_id', '=', 'vd.vnd_venid')
                ->where('vc.ven_cliid', $cliId)
                ->where('vc.ven_estado', '<>', VentaCab::ESTADO_ANULADA)
                ->whereNotNull('vd.vnd_prdid')
                ->where('vc.ven_fchaem', '<', $hasta->toDateString())
                ->groupBy('vd.vnd_prdid')
                ->selectRaw('vd.vnd_prdid AS prd, MAX(vc.ven_fchaem) AS ultima')
                ->get();

            foreach ($compras as $f) {
                $prd = (int) $f->prd;
                $comprados[$prd] = true;
                $pesos[$prd] = $cfg['peso_compra'] * $this->decaimiento($f->ultima, $hasta);
            }
        }

        if ($cliId || $visita) {
            $vistas = DB::table('vista_producto as vp')
                ->leftJoin('usuario as u', 'u.usu_id', '=', 'vp.vpr_usuid')
                ->leftJoin('cliente as cl', 'cl.cli_perid', '=', 'u.usu_perid')
                ->where('vp.vpr_creado', '>=', $hasta->subDays($cfg['dias_perfil']))
                ->where('vp.vpr_creado', '<', $hasta)
                ->where(function ($q) use ($cliId, $visita) {
                    if ($cliId) {
                        $q->orWhere('cl.cli_id', $cliId);
                    }
                    if ($visita) {
                        $q->orWhere('vp.vpr_visita', $visita);
                    }
                })
                ->groupBy('vp.vpr_prdid')
                ->selectRaw('vp.vpr_prdid AS prd, MAX(vp.vpr_creado) AS ultima')
                ->get();

            foreach ($vistas as $f) {
                $prd = (int) $f->prd;
                $peso = $cfg['peso_vista'] * $this->decaimiento($f->ultima, $hasta);
                $pesos[$prd] = max($pesos[$prd] ?? 0.0, $peso);
            }
        }

        return ['pesos' => $pesos, 'comprados' => $comprados];
    }

    /**
     * @param  array<int, array<int, float>> $vecinos    origen => [candidato => similitud]
     * @param  array<int, float>             $tendencias prd => 0..1
     * @return array<int, float> candidato => score final, orden descendente
     */
    public function rankear(array $perfil, array $vecinos, array $tendencias): array
    {
        $alfa = (float) config('recomendacion.peso_tendencia');

        $personal = [];
        foreach ($perfil['pesos'] as $origen => $peso) {
            foreach ($vecinos[$origen] ?? [] as $candidato => $sim) {
                $personal[$candidato] = ($personal[$candidato] ?? 0.0) + $peso * $sim;
            }
        }
        $personal = $this->normalizar($personal);

        $final = [];
        foreach ($personal as $prd => $s) {
            $final[$prd] = (1 - $alfa) * $s + $alfa * ($tendencias[$prd] ?? 0.0);
        }
        // Populares como relleno cuando el historial todavía es corto.
        foreach (array_slice($tendencias, 0, 50, true) as $prd => $t) {
            $final[$prd] ??= $alfa * $t;
        }

        // No recomendar lo que ya compró ni lo que acaba de consultar.
        $final = array_diff_key($final, $perfil['comprados'], $perfil['pesos']);
        arsort($final);

        return $final;
    }

    /** @return array<int, float> prd => popularidad 0..1, orden descendente */
    public function tendencias(?CarbonImmutable $hasta = null): array
    {
        if ($hasta) {
            return $this->calcularTendencias($hasta);
        }

        return Cache::remember(self::CACHE_TENDENCIAS, now()->addHour(),
            fn () => $this->calcularTendencias(CarbonImmutable::tomorrow()));
    }

    // ------------------------------------------------------------------
    // Auxiliares
    // ------------------------------------------------------------------

    private function calcularTendencias(CarbonImmutable $hasta): array
    {
        $cfg = config('recomendacion');
        $desde = $hasta->subDays($cfg['dias_tendencia']);

        // Clientes DISTINTOS (no unidades): un comprador mayorista no infla la tendencia.
        $ventas = DB::table('venta_detalle as vd')
            ->join('venta_cab as vc', 'vc.ven_id', '=', 'vd.vnd_venid')
            ->where('vc.ven_estado', '<>', VentaCab::ESTADO_ANULADA)
            ->whereNotNull('vd.vnd_prdid')
            ->where('vc.ven_fchaem', '>=', $desde->toDateString())
            ->where('vc.ven_fchaem', '<', $hasta->toDateString())
            ->groupBy('vd.vnd_prdid')
            ->selectRaw('vd.vnd_prdid AS prd, COUNT(DISTINCT vc.ven_cliid) AS total')
            ->pluck('total', 'prd');

        $vistas = DB::table('vista_producto')
            ->where('vpr_creado', '>=', $desde)
            ->where('vpr_creado', '<', $hasta)
            ->groupBy('vpr_prdid')
            ->selectRaw('vpr_prdid AS prd, COUNT(*) AS total')
            ->pluck('total', 'prd');

        $score = [];
        foreach ($ventas as $prd => $total) {
            $score[(int) $prd] = (float) $total;
        }
        foreach ($vistas as $prd => $total) {
            $score[(int) $prd] = ($score[(int) $prd] ?? 0.0) + $cfg['peso_vista_tendencia'] * (float) $total;
        }

        arsort($score);

        return $this->normalizar($score);
    }

    /** @return array<int, array<int, float>> */
    private function vecinosDe(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return ProductoSimilitud::whereIn('psi_prdid', $ids)
            ->get(['psi_prdid', 'psi_simid', 'psi_score'])
            ->groupBy('psi_prdid')
            ->map(fn ($g) => $g->mapWithKeys(fn ($f) => [$f->psi_simid => (float) $f->psi_score])->all())
            ->all();
    }

    /** Sin datos de ningún tipo: lo más nuevo publicado (último recurso). */
    private function resultado(string $estrategia, array $ids): array
    {
        if (empty($ids)) {
            $estrategia = 'novedades';
            $ids = PublicacionWeb::publicadas()->orderByDesc('pub_creado')
                ->limit(72)->pluck('pub_prdid')->map(fn ($v) => (int) $v)->all();
        }

        return ['estrategia' => $estrategia, 'ids' => $ids];
    }

    private function decaimiento(string $fecha, CarbonImmutable $hasta): float
    {
        $dias = max(0, Carbon::parse($fecha)->diffInDays($hasta));

        return 2 ** (-$dias / config('recomendacion.vida_media_dias'));
    }

    private function normalizar(array $valores): array
    {
        $max = empty($valores) ? 0 : max($valores);

        return $max > 0 ? array_map(fn ($v) => $v / $max, $valores) : $valores;
    }
}