<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa el renombre cobro_venta -> recibo para el cobro de servicio.
 *
 * 2026_11_05_000006 agregó cob_spaid (y su FK, índice y CHECK) en una rama;
 * 2026_11_12_000001 renombró la tabla en otra, sin conocer esa columna, así
 * que quedó con el prefijo viejo. Igual que con cob_ccoid, se corrige en una
 * migración aparte para no reescribir una que ya pudo haberse ejecutado.
 */
return new class extends Migration
{
    /** Constraints e índices: viejo => nuevo. */
    private const OBJETOS = [
        'fk_cob_spa'        => 'fk_rec_spa',
        'uq_cob_spa_activo' => 'uq_rec_spa_activo',
        'ck_cob_origen'     => 'ck_rec_origen',
    ];

    public function up(): void
    {
        if (Schema::hasColumn('recibo', 'cob_spaid')) {
            DB::statement('ALTER TABLE recibo RENAME COLUMN cob_spaid TO rec_spaid');
        }

        foreach (self::OBJETOS as $viejo => $nuevo) {
            $this->renombrarObjeto($viejo, $nuevo);
        }
    }

    public function down(): void
    {
        foreach (self::OBJETOS as $viejo => $nuevo) {
            $this->renombrarObjeto($nuevo, $viejo);
        }

        if (Schema::hasColumn('recibo', 'rec_spaid')) {
            DB::statement('ALTER TABLE recibo RENAME COLUMN rec_spaid TO cob_spaid');
        }
    }

    /** Mismo criterio que la migración de renombre: se consulta el catálogo. */
    private function renombrarObjeto(string $viejo, string $nuevo): void
    {
        if (DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$viejo])) {
            DB::statement("ALTER TABLE recibo RENAME CONSTRAINT {$viejo} TO {$nuevo}");

            return;
        }

        if (DB::selectOne('SELECT 1 FROM pg_class WHERE relname = ? AND relkind = ?', [$viejo, 'i'])) {
            DB::statement("ALTER INDEX {$viejo} RENAME TO {$nuevo}");
        }
    }
};