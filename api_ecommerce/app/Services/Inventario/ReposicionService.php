<?php
// app/Services/Inventario/ReposicionService.php
namespace App\Services\Inventario;

use App\Models\Configuracion\ConfiguracionTienda;
use App\Models\Inventario\AlertaStock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cruza el stock actual con el pronóstico guardado y mantiene alerta_stock.
 *
 * - σ (variabilidad) = RMSE del modelo elegido en el backtesting. Si no hay
 *   backtesting, se asume demanda Poisson: σ = √demanda.
 * - Solo evalúa combinaciones con demanda prevista > 0. Los productos sin
 *   demanda los cubre el reporte "Sin movimiento" que ya existe.
 * - Sincronización: actualiza la alerta pendiente existente, crea las nuevas
 *   y cierra automáticamente las que dejaron de aplicar.
 * - Una alerta descartada por el usuario no se vuelve a crear con el mismo
 *   tipo durante DIAS_SILENCIO días.
 */
class ReposicionService
{
    public const DIAS_SILENCIO = 7;

    /**
     * Situación de reposición de cada producto/depósito con demanda prevista.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function situaciones(?int $productoId = null, ?int $depositoId = null): Collection
    {
        $cfg = ConfiguracionTienda::actual();
        $z   = CalculoReposicion::factorZ((int) $cfg->ctn_nivserv);
        $lt  = (int) $cfg->ctn_leadtime;
        $rev = (int) $cfg->ctn_horizonte;
        $max = (int) $cfg->ctn_cobmax;

        $prediccion = DB::table('prediccion_demanda')
            ->selectRaw('pdd_prdid, pdd_depid, SUM(pdd_cantid) / COUNT(*) AS demanda_semanal, MAX(pdd_modelo) AS modelo')
            ->groupBy('pdd_prdid', 'pdd_depid');

        return DB::table('stock as st')
            ->joinSub($prediccion, 'pd', fn ($j) => $j->on('pd.pdd_prdid', '=', 'st.sto_prdid')->on('pd.pdd_depid', '=', 'st.sto_depid'))
            ->join('producto as pr', 'pr.prd_id', '=', 'st.sto_prdid')
            ->join('deposito as dp', 'dp.dpo_id', '=', 'st.sto_depid')
            ->leftJoin('metrica_modelo as mm', function ($j) {
                $j->on('mm.mmo_prdid', '=', 'st.sto_prdid')
                  ->on('mm.mmo_depid', '=', 'st.sto_depid')
                  ->where('mm.mmo_elegido', true);
            })
            ->where('pr.prd_estado', 1)
            ->where('pr.prd_afestk', 1)
            ->where('dp.dpo_estado', 1)
            ->where('pd.demanda_semanal', '>', 0)
            ->when($productoId, fn ($q) => $q->where('st.sto_prdid', $productoId))
            ->when($depositoId, fn ($q) => $q->where('st.sto_depid', $depositoId))
            ->get([
                'st.sto_prdid', 'st.sto_depid', 'st.sto_cosprm',
                DB::raw('(st.sto_cantid - st.sto_reserv) AS disponible'),
                'pr.prd_ltdias', 'pd.demanda_semanal', 'pd.modelo', 'mm.mmo_rmse',
            ])
            ->map(function ($f) use ($z, $lt, $rev, $max) {
                $demanda = (float) $f->demanda_semanal;
                $sigma   = $f->mmo_rmse !== null ? (float) $f->mmo_rmse : sqrt($demanda);
                $ltDias  = (int) ($f->prd_ltdias ?: $lt);

                $calculo = CalculoReposicion::calcular((float) $f->disponible, $demanda, $sigma, $ltDias, $z, $rev, $max);

                return [
                    'prd_id'          => (int) $f->sto_prdid,
                    'dep_id'          => (int) $f->sto_depid,
                    'disponible'      => (float) $f->disponible,
                    'demanda_semanal' => round($demanda, 4),
                    'demanda_diaria'  => round($demanda / 7, 4),
                    'sigma'           => round($sigma, 4),
                    'lt_dias'         => $ltDias,
                    'modelo'          => $f->modelo,
                    'costo_unitario'  => (float) $f->sto_cosprm,
                ] + $calculo;
            });
    }

    /**
     * Sincroniza alerta_stock con las situaciones actuales.
     *
     * @return array{creadas: int, actualizadas: int, cerradas: int, por_tipo: array<string, int>}
     */
    public function sincronizarAlertas(): array
    {
        $ahora       = CarbonImmutable::now();
        $situaciones = $this->situaciones()->keyBy(fn ($s) => "{$s['prd_id']}-{$s['dep_id']}");

        $pendientes = AlertaStock::pendientes()->get()->keyBy(fn ($a) => "{$a->ast_prdid}-{$a->ast_depid}");

        $silenciadas = AlertaStock::query()
            ->where('ast_estado', AlertaStock::ESTADO_DESCARTADA)
            ->where('ast_cerrado', '>=', $ahora->subDays(self::DIAS_SILENCIO))
            ->get(['ast_prdid', 'ast_depid', 'ast_tipo'])
            ->map(fn ($a) => "{$a->ast_prdid}-{$a->ast_depid}-{$a->ast_tipo}")
            ->flip();

        $res = ['creadas' => 0, 'actualizadas' => 0, 'cerradas' => 0, 'por_tipo' => []];

        DB::transaction(function () use ($situaciones, $pendientes, $silenciadas, $ahora, &$res) {
            foreach ($situaciones as $clave => $s) {
                if ($s['tipo'] === null || isset($silenciadas["{$clave}-{$s['tipo']}"])) {
                    continue;
                }

                $datos = [
                    'ast_tipo'   => $s['tipo'],
                    'ast_dispon' => $s['disponible'],
                    'ast_demdia' => $s['demanda_diaria'],
                    'ast_ss'     => $s['ss'],
                    'ast_rop'    => $s['rop'],
                    'ast_sugeri' => $s['sugerido'],
                    'ast_cobert' => $s['cobertura_dias'],
                    'ast_ltdias' => $s['lt_dias'],
                    'ast_modelo' => $s['modelo'],
                    'ast_actual' => $ahora,
                ];

                if ($alerta = $pendientes->get($clave)) {
                    $alerta->update($datos);
                    $res['actualizadas']++;
                } else {
                    AlertaStock::create($datos + [
                        'ast_prdid'  => $s['prd_id'],
                        'ast_depid'  => $s['dep_id'],
                        'ast_estado' => AlertaStock::ESTADO_PENDIENTE,
                        'ast_creado' => $ahora,
                    ]);
                    $res['creadas']++;
                }

                $res['por_tipo'][$s['tipo']] = ($res['por_tipo'][$s['tipo']] ?? 0) + 1;
            }

            // Cierre automático: la alerta pendiente ya no aplica.
            foreach ($pendientes as $clave => $alerta) {
                $s = $situaciones->get($clave);
                if ($s !== null && $s['tipo'] !== null) {
                    continue;
                }

                $alerta->update([
                    'ast_estado'  => AlertaStock::ESTADO_ATENDIDA,
                    'ast_obs'     => 'Cerrada automáticamente: el stock volvió a un nivel normal.',
                    'ast_actual'  => $ahora,
                    'ast_cerrado' => $ahora,
                ]);
                $res['cerradas']++;
            }
        });

        return $res;
    }
}