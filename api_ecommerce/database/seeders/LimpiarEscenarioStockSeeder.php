<?php
// database/seeders/LimpiarEscenarioStockSeeder.php
namespace Database\Seeders;

use App\Models\Compras\AjusteInventario;
use App\Models\Usuario;
use App\Services\Compras\AjusteService;
use App\Services\Inventario\ReposicionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Revierte EscenarioStockInventarioSeeder anulando sus ajustes
 * (AjusteService::anular devuelve el stock y deja la reversa en el kardex).
 *   php artisan db:seed --class=LimpiarEscenarioStockSeeder
 */
class LimpiarEscenarioStockSeeder extends Seeder
{
    public function run(AjusteService $ajustes, ReposicionService $reposicion): void
    {
        Auth::setUser(Usuario::orderBy('usu_id')->firstOrFail());

        $pendientes = AjusteInventario::where('aji_referencia', EscenarioStockInventarioSeeder::REFERENCIA)
            ->where('aji_estado', AjusteInventario::ESTADO_CONFIRMADO)
            ->orderByDesc('aji_id')
            ->get();

        foreach ($pendientes as $ajuste) {
            try {
                $ajustes->anular($ajuste);
                $this->command->info("Ajuste #{$ajuste->aji_id} anulado.");
            } catch (\DomainException $e) {
                // Ocurre si hubo ventas/compras de esos productos después del escenario.
                $this->command->error("Ajuste #{$ajuste->aji_id}: {$e->getMessage()}");
            }
        }

        $res = $reposicion->sincronizarAlertas();
        $this->command->info("Alertas recalculadas: {$res['creadas']} creadas · {$res['cerradas']} cerradas");
    }
}