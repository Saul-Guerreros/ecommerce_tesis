<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modo y costo de envio, configurables por el administrador.
 * ctn_envio_gratis_desde = 0 significa desactivado.
 */
return new class extends Migration
{
        public function up(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            $table->string('ctn_envio_modo', 10)->default('fijo')->after('ctn_pocstk_umbral');
            $table->decimal('ctn_envio_costo', 14, 2)->default(0)->after('ctn_envio_modo');
            $table->decimal('ctn_envio_gratis_desde', 14, 2)->default(0)->after('ctn_envio_costo');
        });

        // La fila ya existente nace con costo 0: se deja en modo "gratis"
        // para no violar el CHECK de más abajo (modo fijo exige costo > 0).
        // El administrador lo cambia desde el panel cuando quiera cobrar envío.
        DB::statement("UPDATE configuracion_tienda SET ctn_envio_modo = 'gratis' WHERE ctn_envio_costo = 0");

        DB::statement("ALTER TABLE configuracion_tienda
            ADD CONSTRAINT ck_ctn_envio_modo CHECK (ctn_envio_modo IN ('gratis','fijo'))");

        DB::statement("ALTER TABLE configuracion_tienda
            ADD CONSTRAINT ck_ctn_envio_costo CHECK (ctn_envio_costo >= 0)");

        DB::statement("ALTER TABLE configuracion_tienda
            ADD CONSTRAINT ck_ctn_envio_gratis_desde CHECK (ctn_envio_gratis_desde >= 0)");

        DB::statement("ALTER TABLE configuracion_tienda
            ADD CONSTRAINT ck_ctn_envio_fijo_costo
            CHECK (ctn_envio_modo <> 'fijo' OR ctn_envio_costo > 0)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE configuracion_tienda DROP CONSTRAINT IF EXISTS ck_ctn_envio_fijo_costo');
        DB::statement('ALTER TABLE configuracion_tienda DROP CONSTRAINT IF EXISTS ck_ctn_envio_gratis_desde');
        DB::statement('ALTER TABLE configuracion_tienda DROP CONSTRAINT IF EXISTS ck_ctn_envio_costo');
        DB::statement('ALTER TABLE configuracion_tienda DROP CONSTRAINT IF EXISTS ck_ctn_envio_modo');

        Schema::table('configuracion_tienda', function (Blueprint $table) {
            $table->dropColumn(['ctn_envio_modo', 'ctn_envio_costo', 'ctn_envio_gratis_desde']);
        });
    }
};