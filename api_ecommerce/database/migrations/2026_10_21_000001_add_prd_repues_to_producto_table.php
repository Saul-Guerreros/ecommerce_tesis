<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Servicio Técnico: distingue los REPUESTOS DE TALLER de los productos de venta.
 *
 * prd_repues = 1 -> repuesto de taller: lo ofrece el buscador del técnico en el
 *                   presupuesto y NO se puede publicar en la tienda.
 * prd_repues = 0 -> producto de venta (valor por defecto; no cambia nada para
 *                   los productos existentes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->smallInteger('prd_repues')->default(0);
        });

        DB::statement('ALTER TABLE producto ADD CONSTRAINT ck_prd_repues CHECK (prd_repues IN (0, 1))');
        DB::statement('CREATE INDEX ix_prd_repues ON producto (prd_repues)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ix_prd_repues');
        DB::statement('ALTER TABLE producto DROP CONSTRAINT IF EXISTS ck_prd_repues');

        Schema::table('producto', function (Blueprint $table) {
            $table->dropColumn('prd_repues');
        });
    }
};
