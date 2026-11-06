<?php
// database/seeders/EscenarioStockInventarioSeeder.php
namespace Database\Seeders;

use App\Models\Compras\AjusteInventario;
use App\Models\Compras\TipoAjuste;
use App\Models\Inventario\AlertaStock;
use App\Models\Usuario;
use App\Services\Compras\AjusteService;
use App\Services\Inventario\ReposicionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Escenario DEMO de stock para mostrar los 4 tipos de alerta.
 *
 * Toma los productos con más demanda prevista del depósito principal y
 * fija su stock con AJUSTES DE INVENTARIO reales (AjusteService), así el
 * kardex queda coherente y el escenario se puede revertir anulándolos:
 *
 *   agotado   -> disponible 0
 *   critico   -> disponible por debajo del stock de seguridad
 *   reponer   -> disponible entre el SS y el punto de reorden
 *   normal    -> productos en sobrestock llevados a su nivel objetivo
 *   sobrestock-> el resto queda como está (stock demo de 30 u. en baja rotación)
 *
 * Requisito: haber ejecutado "php artisan inventario:pronosticar".
 * Reversión: php artisan db:seed --class=LimpiarEscenarioStockSeeder
 */
class EscenarioStockInventarioSeeder extends Seeder
{
    public const REFERENCIA = 'DEMO-ESCENARIO';

    /** Cantidad de productos por escenario. */
    private const CANTIDAD = ['agotado' => 3, 'critico' => 5, 'reponer' => 8, 'normal' => 20];

    public function run(AjusteService $ajustes, ReposicionService $reposicion): void
    {
        if (AjusteInventario::where('aji_referencia', self::REFERENCIA)
                ->where('aji_estado', AjusteInventario::ESTADO_CONFIRMADO)->exists()) {
            $this->command->warn('El escenario ya está aplicado. Revertilo primero con LimpiarEscenarioStockSeeder.');
            return;
        }

        $usuario = Usuario::orderBy('usu_id')->first() ?? throw new RuntimeException('No hay usuarios.');
        Auth::setUser($usuario); // AjusteService registra el usuario en el kardex

        $situaciones = $reposicion->situaciones()
            ->filter(fn ($s) => $s['demanda_semanal'] >= 1)
            ->sortByDesc('demanda_semanal')
            ->values();

        if ($situaciones->count() < self::CANTIDAD['agotado'] + self::CANTIDAD['critico'] + self::CANTIDAD['reponer']) {
            throw new RuntimeException('No hay suficientes productos con demanda. ¿Ejecutaste inventario:pronosticar?');
        }

        // Los productos de mayor demanda se reparten entre agotado, crítico y reponer.
        $plan = [];
        $i = 0;
        foreach (['agotado', 'critico', 'reponer'] as $escenario) {
            foreach ($situaciones->slice($i, self::CANTIDAD[$escenario]) as $s) {
                $plan[] = ['s' => $s, 'escenario' => $escenario, 'disponible' => $this->objetivo($escenario, $s)];
            }
            $i += self::CANTIDAD[$escenario];
        }

        // "normal": productos hoy en sobrestock que se llevan a su nivel objetivo.
        $sobrestock = $situaciones->slice($i)
            ->where('tipo', AlertaStock::TIPO_SOBRESTOCK)
            ->take(self::CANTIDAD['normal']);
        foreach ($sobrestock as $s) {
            $plan[] = ['s' => $s, 'escenario' => 'normal', 'disponible' => $this->objetivo('normal', $s)];
        }

        // Faltantes y sobrantes van en ajustes separados (el tipo exige el signo).
        $lineas = ['N' => [], 'P' => []];
        foreach ($plan as $p) {
            // El ajuste trabaja sobre sto_cantid (físico); la reserva se respeta.
            $s       = $p['s'];
            $reserva = $this->reserva($s);
            $fisico  = $p['disponible'] + $reserva;
            $actual  = $s['disponible'] + $reserva;
            $natura  = $fisico < $actual ? 'N' : ($fisico > $actual ? 'P' : null);

            if ($natura !== null) {
                $lineas[$natura][$s['dep_id']][] = [
                    'prd_id'  => $s['prd_id'],
                    'cantfis' => $fisico,
                    'costo'   => $s['costo_unitario'] > 0 ? $s['costo_unitario'] : null,
                    'obs'     => "Escenario: {$p['escenario']}",
                ];
            }
        }

        $tipos = ['N' => 'MER', 'P' => 'SOB'];
        foreach ($lineas as $natura => $porDeposito) {
            $tipo = TipoAjuste::where('tpa_codig', $tipos[$natura])->firstOrFail();

            foreach ($porDeposito as $depositoId => $detalle) {
                $ajuste = $ajustes->store([
                    'aji_tpaid'      => $tipo->tpa_id,
                    'aji_depid'      => $depositoId,
                    'aji_fecha'      => now()->toDateString(),
                    'aji_referencia' => self::REFERENCIA,
                    'aji_obs'        => 'Escenario demo inventario inteligente',
                ], $detalle, $depositoId);

                $ajustes->confirmar($ajuste);
            }
        }

        $resultado = $reposicion->sincronizarAlertas();

        $this->command->table(['Escenario', 'Producto', 'Disponible nuevo', 'SS', 'ROP'], array_map(fn ($p) => [
            $p['escenario'], $p['s']['prd_id'], $p['disponible'], round($p['s']['ss'], 1), round($p['s']['rop'], 1),
        ], $plan));

        $pendientes = AlertaStock::pendientes()->selectRaw('ast_tipo, COUNT(*) AS n')->groupBy('ast_tipo')->pluck('n', 'ast_tipo');
        $this->command->info("Alertas: {$resultado['creadas']} creadas · {$resultado['actualizadas']} actualizadas · {$resultado['cerradas']} cerradas");
        $this->command->table(['Tipo', 'Pendientes'], $pendientes->map(fn ($n, $t) => [$t, $n])->values()->all());
    }

    /** Disponible deseado para cada escenario (unidades enteras). */
    private function objetivo(string $escenario, array $s): float
    {
        return match ($escenario) {
            'agotado' => 0.0,
            'critico' => max(1.0, floor($s['ss'] * 0.5)),
            'reponer' => max(floor($s['ss']) + 1, floor(($s['ss'] + $s['rop']) / 2)),
            'normal'  => ceil($s['objetivo']),
        };
    }

    private function reserva(array $s): float
    {
        return (float) DB::table('stock')
            ->where('sto_prdid', $s['prd_id'])->where('sto_depid', $s['dep_id'])
            ->value('sto_reserv');
    }
}