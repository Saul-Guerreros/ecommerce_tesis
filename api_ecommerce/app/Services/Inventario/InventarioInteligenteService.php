<?php
// app/Services/Inventario/InventarioInteligenteService.php
namespace App\Services\Inventario;

use App\Models\Configuracion\ConfiguracionTienda;
use App\Models\Inventario\AlertaStock;
use App\Models\Producto;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Consultas y acciones del módulo para la API (el controlador queda delgado).
 */
class InventarioInteligenteService
{
    private const NOMBRE_PRODUCTO = 'COALESCE(pw.pub_titulo, LEFT(pr.prd_descri, 120))';
    private const TIPOS_REPOSICION = [AlertaStock::TIPO_AGOTADO, AlertaStock::TIPO_CRITICO, AlertaStock::TIPO_REPONER];

    public function __construct(
        private readonly SerieDemandaService $series,
        private readonly ReposicionService $reposicion,
        private readonly PronosticoService $pronostico,
    ) {
    }

    // =====================================================================
    // Consultas
    // =====================================================================

    /** Tarjetas del panel. */
    public function resumen(): array
    {
        $porTipo = AlertaStock::pendientes()
            ->selectRaw('ast_tipo, COUNT(*) AS n')
            ->groupBy('ast_tipo')
            ->pluck('n', 'ast_tipo');

        $inversion = DB::table('alerta_stock as a')
            ->join('stock as st', fn ($j) => $j->on('st.sto_prdid', '=', 'a.ast_prdid')->on('st.sto_depid', '=', 'a.ast_depid'))
            ->where('a.ast_estado', AlertaStock::ESTADO_PENDIENTE)
            ->whereIn('a.ast_tipo', self::TIPOS_REPOSICION)
            ->sum(DB::raw('a.ast_sugeri * st.sto_cosprm'));

        $inmovilizado = DB::table('alerta_stock as a')
            ->join('stock as st', fn ($j) => $j->on('st.sto_prdid', '=', 'a.ast_prdid')->on('st.sto_depid', '=', 'a.ast_depid'))
            ->where('a.ast_estado', AlertaStock::ESTADO_PENDIENTE)
            ->where('a.ast_tipo', AlertaStock::TIPO_SOBRESTOCK)
            ->sum(DB::raw('a.ast_dispon * st.sto_cosprm'));

        return [
            'alertas'             => collect(AlertaStock::TIPOS)->mapWithKeys(fn ($t) => [$t => (int) ($porTipo[$t] ?? 0)]),
            'total_pendientes'    => (int) $porTipo->sum(),
            'inversion_sugerida'  => round((float) $inversion, 2),
            'valor_sobrestock'    => round((float) $inmovilizado, 2),
            'productos_analizados'=> (int) DB::table('prediccion_demanda')->distinct()->count(DB::raw('(pdd_prdid, pdd_depid)')),
            'ultima_ejecucion'    => DB::table('prediccion_demanda')->max('pdd_creado'),
            'modelos_elegidos'    => DB::table('metrica_modelo')->where('mmo_elegido', true)
                ->selectRaw('mmo_modelo, COUNT(*) AS n')->groupBy('mmo_modelo')->pluck('n', 'mmo_modelo'),
        ];
    }

        /** Campana del header: conteos de reposición y las 5 alertas más urgentes. */
    public function notificaciones(): array
    {
        $porTipo = AlertaStock::pendientes()
            ->selectRaw('ast_tipo, COUNT(*) AS n')
            ->groupBy('ast_tipo')
            ->pluck('n', 'ast_tipo');

        $urgentes = DB::table('alerta_stock as a')
            ->join('producto as pr', 'pr.prd_id', '=', 'a.ast_prdid')
            ->leftJoin('publicacion_web as pw', 'pw.pub_prdid', '=', 'pr.prd_id')
            ->join('deposito as d', 'd.dpo_id', '=', 'a.ast_depid')
            ->where('a.ast_estado', AlertaStock::ESTADO_PENDIENTE)
            ->whereIn('a.ast_tipo', self::TIPOS_REPOSICION)
            ->selectRaw('a.ast_id AS id, a.ast_tipo AS tipo, ' . self::NOMBRE_PRODUCTO . ' AS producto,
                         d.dpo_nombre AS deposito, a.ast_dispon AS disponible, a.ast_sugeri AS sugerido,
                         a.ast_cobert AS cobertura_dias')
            ->orderByRaw("CASE a.ast_tipo WHEN 'agotado' THEN 1 WHEN 'critico' THEN 2 ELSE 3 END")
            ->orderBy('a.ast_cobert')
            ->limit(5)
            ->get()
            ->map(fn ($a) => [
                'id'             => (int) $a->id,
                'tipo'           => $a->tipo,
                'producto'       => $a->producto,
                'deposito'       => $a->deposito,
                'disponible'     => (float) $a->disponible,
                'sugerido'       => (float) $a->sugerido,
                'cobertura_dias' => $a->cobertura_dias !== null ? (float) $a->cobertura_dias : null,
            ]);

        $agotado = (int) ($porTipo[AlertaStock::TIPO_AGOTADO] ?? 0);
        $critico = (int) ($porTipo[AlertaStock::TIPO_CRITICO] ?? 0);
        $reponer = (int) ($porTipo[AlertaStock::TIPO_REPONER] ?? 0);

        return [
            'agotado'          => $agotado,
            'critico'          => $critico,
            'reponer'          => $reponer,
            'total_reposicion' => $agotado + $critico + $reponer,
            'urgentes'         => $urgentes,
        ];
    }

    /** Listado de alertas con filtros. */
    public function alertas(array $filtros): array
    {
        $estado = $filtros['estado'] ?? AlertaStock::ESTADO_PENDIENTE;

        return DB::table('alerta_stock as a')
            ->join('producto as pr', 'pr.prd_id', '=', 'a.ast_prdid')
            ->leftJoin('publicacion_web as pw', 'pw.pub_prdid', '=', 'pr.prd_id')
            ->join('deposito as d', 'd.dpo_id', '=', 'a.ast_depid')
            ->leftJoin('stock as st', fn ($j) => $j->on('st.sto_prdid', '=', 'a.ast_prdid')->on('st.sto_depid', '=', 'a.ast_depid'))
            ->leftJoin('usuario as u', 'u.usu_id', '=', 'a.ast_usuid')
            ->where('a.ast_estado', $estado)
            ->when($filtros['tipo'] ?? null, fn ($q, $t) => $q->where('a.ast_tipo', $t))
            ->when($filtros['deposito'] ?? null, fn ($q, $d) => $q->where('a.ast_depid', $d))
            ->when($filtros['search'] ?? null, function ($q, $s) {
                $like = '%' . mb_strtolower($s) . '%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(' . self::NOMBRE_PRODUCTO . ') LIKE ?', [$like])
                                     ->orWhereRaw('LOWER(pr.prd_codinv) LIKE ?', [$like]));
            })
            ->selectRaw('a.*, pr.prd_codinv, ' . self::NOMBRE_PRODUCTO . ' AS producto,
                         d.dpo_nombre AS deposito, st.sto_cosprm AS costo, u.usu_login AS usuario')
            // Urgencia: agotado > crítico > reponer > sobrestock; luego menor cobertura.
            ->orderByRaw("CASE a.ast_tipo WHEN 'agotado' THEN 1 WHEN 'critico' THEN 2 WHEN 'reponer' THEN 3 ELSE 4 END")
            ->orderByRaw('a.ast_cobert ASC NULLS FIRST')
            ->get()
            ->map(fn ($a) => [
                'id'              => (int) $a->ast_id,
                'producto_id'     => (int) $a->ast_prdid,
                'codigo'          => $a->prd_codinv,
                'producto'        => $a->producto,
                'deposito_id'     => (int) $a->ast_depid,
                'deposito'        => $a->deposito,
                'tipo'            => $a->ast_tipo,
                'disponible'      => (float) $a->ast_dispon,
                'demanda_diaria'  => (float) $a->ast_demdia,
                'stock_seguridad' => (float) $a->ast_ss,
                'punto_reorden'   => (float) $a->ast_rop,
                'sugerido'        => (float) $a->ast_sugeri,
                'costo_sugerido'  => round((float) $a->ast_sugeri * (float) $a->costo, 2),
                'cobertura_dias'  => $a->ast_cobert !== null ? (float) $a->ast_cobert : null,
                'lead_time'       => (int) $a->ast_ltdias,
                'modelo'          => $a->ast_modelo,
                'estado'          => (int) $a->ast_estado,
                'observacion'     => $a->ast_obs,
                'usuario'         => $a->usuario,
                'creado'          => $a->ast_creado,
                'actualizado'     => $a->ast_actual,
                'cerrado'         => $a->ast_cerrado,
            ])
            ->all();
    }

    /** Historial, pronóstico, cálculo de reposición y métricas de un producto. */
    public function pronosticoProducto(int $productoId, ?int $depositoId = null): array
    {
        $producto = Producto::findOrFail($productoId);

        $depositoId ??= DB::table('prediccion_demanda')->where('pdd_prdid', $productoId)->value('pdd_depid')
            ?? DB::table('deposito as d')->join('sucursal as s', 's.suc_id', '=', 'd.dpo_sucid')
                ->where('s.suc_princi', 1)->where('d.dpo_princi', 1)->value('d.dpo_id');

        $serie = $this->series->deProducto($productoId, (int) $depositoId);

        return [
            'producto' => [
                'id'        => $producto->prd_id,
                'codigo'    => $producto->prd_codinv,
                'nombre'    => $producto->prd_descri,
                'lead_time' => $producto->prd_ltdias,
            ],
            'deposito_id'   => (int) $depositoId,
            'clasificacion' => $serie->estaVacia() ? null : $serie->clasificacion(),
            'adi'           => $serie->adi(),
            'historial'     => array_map(fn ($s, $v) => ['semana' => $s, 'unidades' => $v], $serie->semanas(), $serie->datos()),
            'pronostico'    => DB::table('prediccion_demanda')
                ->where('pdd_prdid', $productoId)->where('pdd_depid', $depositoId)
                ->orderBy('pdd_periodo')
                ->get(['pdd_periodo AS semana', 'pdd_cantid AS unidades', 'pdd_modelo AS modelo'])
                ->map(fn ($p) => ['semana' => $p->semana, 'unidades' => (float) $p->unidades, 'modelo' => $p->modelo]),
            'reposicion'    => $this->reposicion->situaciones($productoId, (int) $depositoId)->first(),
            'metricas'      => DB::table('metrica_modelo')
                ->where('mmo_prdid', $productoId)->where('mmo_depid', $depositoId)
                ->orderBy('mmo_rmse')
                ->get(['mmo_modelo AS modelo', 'mmo_rmse AS rmse', 'mmo_mae AS mae', 'mmo_wape AS wape', 'mmo_elegido AS elegido']),
        ];
    }

    /** Desempeño global de cada modelo (para la pantalla de métricas). */
    public function metricas(): array
    {
        return DB::table('metrica_modelo')
            ->selectRaw('mmo_modelo AS modelo,
                         COUNT(*) FILTER (WHERE mmo_elegido) AS veces_elegido,
                         ROUND(AVG(mmo_rmse), 4) AS rmse,
                         ROUND(AVG(mmo_mae), 4)  AS mae,
                         ROUND(SUM(mmo_mae * mmo_semprb) / NULLIF(SUM(mmo_semprb), 0), 4) AS mae_ponderado')
            ->groupBy('mmo_modelo')
            ->orderBy('rmse')
            ->get()
            ->all();
    }

    public function config(): array
    {
        $c = ConfiguracionTienda::actual();

        return [
            'nivel_servicio' => (int) $c->ctn_nivserv,
            'factor_z'       => CalculoReposicion::factorZ((int) $c->ctn_nivserv),
            'horizonte'      => (int) $c->ctn_horizonte,
            'lead_time'      => (int) $c->ctn_leadtime,
            'cobertura_max'  => (int) $c->ctn_cobmax,
        ];
    }

    // =====================================================================
    // Acciones
    // =====================================================================

    public function cerrarAlerta(int $id, int $estado, int $usuarioId, ?string $obs): AlertaStock
    {
        $alerta = AlertaStock::findOrFail($id);

        if (! $alerta->estaPendiente()) {
            throw new DomainException('Solo se pueden cerrar alertas pendientes.');
        }

        $alerta->update([
            'ast_estado'  => $estado,
            'ast_usuid'   => $usuarioId,
            'ast_obs'     => $obs,
            'ast_cerrado' => CarbonImmutable::now(),
        ]);

        return $alerta;
    }

    /** Pronóstico completo + alertas. */
    public function recalcular(): array
    {
        $resumen = $this->pronostico->ejecutar();

        return ['pronostico' => $resumen, 'alertas' => $this->reposicion->sincronizarAlertas()];
    }

    /**
     * Guarda los parámetros. Si cambia el horizonte hay que volver a
     * pronosticar; el resto solo afecta al cálculo de alertas.
     */
    public function actualizarConfig(array $datos): array
    {
        $c = ConfiguracionTienda::actual();
        $cambiaHorizonte = isset($datos['ctn_horizonte']) && (int) $datos['ctn_horizonte'] !== (int) $c->ctn_horizonte;

        $c->update($datos);

        $cambiaHorizonte ? $this->recalcular() : $this->reposicion->sincronizarAlertas();

        return $this->config();
    }

    public function actualizarLeadTime(int $productoId, ?int $dias): void
    {
        Producto::whereKey($productoId)->update(['prd_ltdias' => $dias]);
        $this->reposicion->sincronizarAlertas();
    }
}