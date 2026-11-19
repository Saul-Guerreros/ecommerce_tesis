<?php

namespace App\Console\Commands;

use App\Models\PublicacionWeb;
use App\Services\Recomendacion\EntrenadorSimilitudService;
use App\Services\Recomendacion\RecomendacionService;
use App\Models\Venta\VentaCab;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Evaluación offline (validación temporal / hold-out):
 *  - Entrena con los datos ANTERIORES al corte.
 *  - Mide si lo recomendado aparece en lo que el cliente compró DESPUÉS.
 *  - Compara contra una línea base (solo tendencias).
 *
 *   php artisan recomendaciones:evaluar --dias=30 --k=10
 */
class EvaluarRecomendaciones extends Command
{
    protected $signature = 'recomendaciones:evaluar
        {--dias=30 : Días finales usados como conjunto de prueba}
        {--k=10 : Tamaño de la lista recomendada}';

    protected $description = 'Calcula HitRate@K, Precision@K, Recall@K y Cobertura del recomendador';

    public function handle(EntrenadorSimilitudService $entrenador, RecomendacionService $servicio): int
    {
        $k = max(1, (int) $this->option('k'));
        $corte = CarbonImmutable::today()->subDays(max(1, (int) $this->option('dias')));

        // 1) Modelo entrenado solo con el "pasado" (no toca la tabla real).
        $vecinos = array_map(
            fn ($lista) => array_map(fn ($d) => $d['score'], $lista),
            $entrenador->similitudes($entrenador->interacciones($corte))
        );
        $tendencias = $servicio->tendencias($corte);

        // 2) Conjunto de prueba: compras posteriores al corte, por cliente.
        $prueba = DB::table('venta_detalle as vd')
            ->join('venta_cab as vc', 'vc.ven_id', '=', 'vd.vnd_venid')
            ->where('vc.ven_estado', '<>', VentaCab::ESTADO_ANULADA)
            ->whereNotNull('vd.vnd_prdid')
            ->where('vc.ven_fchaem', '>=', $corte->toDateString())
            ->select('vc.ven_cliid as cli', 'vd.vnd_prdid as prd')
            ->distinct()
            ->get()
            ->groupBy('cli')
            ->map(fn ($g) => $g->pluck('prd')->map(fn ($v) => (int) $v)->flip()->all());

        $nuevo = fn () => ['n' => 0, 'hits' => 0, 'precision' => 0.0, 'recall' => 0.0, 'items' => []];
        $res = ['hibrido' => $nuevo(), 'base' => $nuevo()];
        $enFrio = 0;

        foreach ($prueba as $cli => $comprados) {
            $perfil = $servicio->perfil((int) $cli, null, $corte);

            // Solo cuenta como acierto un producto NUEVO para ese cliente.
            $objetivo = array_diff_key($comprados, $perfil['comprados']);
            if (empty($objetivo)) {
                continue;
            }
            if (empty($perfil['pesos'])) {
                $enFrio++;
                continue;
            }

            $listas = [
                'hibrido' => array_slice(array_keys($servicio->rankear($perfil, $vecinos, $tendencias)), 0, $k),
                'base'    => array_slice(array_keys(array_diff_key($tendencias, $perfil['comprados'], $perfil['pesos'])), 0, $k),
            ];

            foreach ($listas as $modelo => $lista) {
                $aciertos = count(array_intersect_key(array_flip($lista), $objetivo));
                $res[$modelo]['n']++;
                $res[$modelo]['hits'] += $aciertos > 0 ? 1 : 0;
                $res[$modelo]['precision'] += $aciertos / $k;
                $res[$modelo]['recall'] += $aciertos / count($objetivo);
                $res[$modelo]['items'] += array_fill_keys($lista, true);
            }
        }

        if ($res['hibrido']['n'] === 0) {
            $this->warn('No hay clientes con historial antes y compras después del corte. Probá con más --dias.');
            return self::SUCCESS;
        }

        $catalogo = max(1, PublicacionWeb::publicadas()->count());
        $pct = fn (float $v) => number_format($v * 100, 2).' %';
        $nombres = ['hibrido' => 'Híbrido (CF ítem-ítem + tendencias)', 'base' => 'Línea base (solo tendencias)'];

        $this->info("Corte: {$corte->toDateString()} | K = {$k}");
        $this->table(
            ['Modelo', 'Clientes', "HitRate@{$k}", "Precision@{$k}", "Recall@{$k}", 'Cobertura'],
            collect($nombres)->map(fn ($nombre, $m) => [
                $nombre,
                $res[$m]['n'],
                $pct($res[$m]['hits'] / $res[$m]['n']),
                $pct($res[$m]['precision'] / $res[$m]['n']),
                $pct($res[$m]['recall'] / $res[$m]['n']),
                $pct(count($res[$m]['items']) / $catalogo),
            ])->values()->all()
        );
        $this->line("Clientes sin historial previo (arranque en frío, excluidos): {$enFrio}");

        return self::SUCCESS;
    }
}