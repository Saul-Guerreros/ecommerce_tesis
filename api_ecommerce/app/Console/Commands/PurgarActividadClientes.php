<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Política de retención del análisis de comportamiento: borra búsquedas y
 * vistas más antiguas que N meses (por defecto 24). Se programa en Kernel.
 *
 *   php artisan actividad:purgar --meses=24
 */
class PurgarActividadClientes extends Command
{
    protected $signature = 'actividad:purgar {--meses=24 : Antigüedad máxima a conservar}';

    protected $description = 'Elimina búsquedas y vistas de producto más antiguas que el período de retención';

    public function handle(): int
    {
        $meses = max(3, (int) $this->option('meses'));
        $limite = now()->subMonthsNoOverflow($meses);

        // Primero las vistas: referencian a la búsqueda.
        $vistas = DB::table('vista_producto')->where('vpr_creado', '<', $limite)->delete();
        $busquedas = DB::table('busqueda')->where('bus_creado', '<', $limite)->delete();

        $this->info("Retención {$meses} meses: {$vistas} vista(s) y {$busquedas} búsqueda(s) eliminadas.");

        return self::SUCCESS;
    }
}