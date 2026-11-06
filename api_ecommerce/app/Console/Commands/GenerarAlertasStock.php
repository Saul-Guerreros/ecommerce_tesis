<?php
// app/Console/Commands/GenerarAlertasStock.php
namespace App\Console\Commands;

use App\Services\Inventario\ReposicionService;
use Illuminate\Console\Command;
use App\Services\Inventario\NotificadorInventario;

/**
 * Recalcula las alertas de reposición con el stock actual y el último
 * pronóstico guardado (no vuelve a pronosticar).
 *   php artisan inventario:alertas
 *   php artisan inventario:alertas --producto=165
 */
class GenerarAlertasStock extends Command
{
    protected $signature = 'inventario:alertas
        {--producto= : Solo muestra el cálculo de este producto (no guarda)}';

    protected $description = 'Genera/actualiza las alertas de reposición según stock y pronóstico';

    public function handle(ReposicionService $servicio, NotificadorInventario $notificador): int
    {
        if ($productoId = $this->option('producto')) {
            return $this->detalle($servicio, (int) $productoId);
        }

        $res = $servicio->sincronizarAlertas();
        $avisadas = $notificador->avisarUrgentes();

        $this->info("Alertas: {$res['creadas']} creadas · {$res['actualizadas']} actualizadas · {$res['cerradas']} cerradas automáticamente");
        $this->table(['Tipo', 'Pendientes'], collect($res['por_tipo'])
            ->map(fn ($n, $tipo) => [$tipo, $n])->values()->all());
        $this->line("Avisos por correo: {$avisadas} alerta(s) urgente(s) notificada(s)");

        return self::SUCCESS;
    }

    private function detalle(ReposicionService $servicio, int $productoId): int
    {
        $situaciones = $servicio->situaciones($productoId);
        if ($situaciones->isEmpty()) {
            $this->warn('Sin pronóstico con demanda > 0 para ese producto. ¿Ejecutaste inventario:pronosticar?');
            return self::SUCCESS;
        }

        foreach ($situaciones as $s) {
            $this->table(['Concepto', 'Valor'], [
                ['Producto / Depósito', "{$s['prd_id']} / {$s['dep_id']}"],
                ['Modelo', $s['modelo']],
                ['Disponible', $s['disponible']],
                ['Demanda semanal prevista', $s['demanda_semanal']],
                ['σ semanal (RMSE)', $s['sigma']],
                ['Lead time (días)', $s['lt_dias']],
                ['Stock de seguridad (SS)', $s['ss']],
                ['Punto de reorden (ROP)', $s['rop']],
                ['Nivel objetivo (S)', $s['objetivo']],
                ['Cobertura (días)', $s['cobertura_dias'] ?? '-'],
                ['Cantidad sugerida', $s['sugerido']],
                ['Resultado', $s['tipo'] ?? 'sin alerta'],
            ]);
        }

        return self::SUCCESS;
    }
}