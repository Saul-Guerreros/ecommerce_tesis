<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Tabla singleton (mismo criterio que configuracion_precio / configuracion_tienda). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_envio', function (Blueprint $table) {
            $table->increments('cfe_id');
            $table->string('cfe_modo', 10)->default('fijo');
            $table->decimal('cfe_costo_fijo', 10, 2)->nullable();
            $table->decimal('cfe_monto_minimo', 10, 2)->nullable();
            $table->timestamp('cfe_creado')->useCurrent();
            $table->timestamp('cfe_actualizado')->useCurrent()->useCurrentOnUpdate();
        });

        DB::statement("ALTER TABLE configuracion_envio
            ADD CONSTRAINT ck_cfe_modo CHECK (cfe_modo IN ('gratis','fijo','sucursal'))");

        DB::statement("ALTER TABLE configuracion_envio
            ADD CONSTRAINT ck_cfe_costo_fijo CHECK (cfe_costo_fijo IS NULL OR cfe_costo_fijo >= 0)");

        DB::statement("ALTER TABLE configuracion_envio
            ADD CONSTRAINT ck_cfe_monto_minimo CHECK (cfe_monto_minimo IS NULL OR cfe_monto_minimo >= 0)");

        DB::statement("ALTER TABLE configuracion_envio
            ADD CONSTRAINT ck_cfe_fijo_requiere_costo
            CHECK (cfe_modo <> 'fijo' OR cfe_costo_fijo IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_envio');
    }
};