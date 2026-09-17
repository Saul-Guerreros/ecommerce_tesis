<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tabla singleton: configuración general de markup para toda la
     * tienda (Gestión de Precios). Se espera una única fila; el control
     * de unicidad se hace en ConfiguracionPrecioService, no acá.
     *
     * Ver Contexto_Markup_General.md: markup general por defecto, con
     * posibilidad de override individual por producto (prd_marmin,
     * prd_marmaj, prd_canminmaj — ver migración siguiente).
     */
    public function up(): void
    {
        Schema::create('configuracion_precio', function (Blueprint $table) {
            $table->increments('cpr_id');
            $table->decimal('cpr_marmin', 14, 4);     // margen minorista general (%)
            $table->decimal('cpr_marmaj', 14, 4);     // margen mayorista general (%)
            $table->decimal('cpr_canminmaj', 14, 4);  // cantidad mínima mayorista general
            $table->timestamp('cpr_creado')->useCurrent();
        });

        DB::statement("ALTER TABLE configuracion_precio
            ADD CONSTRAINT ck_cpr_marmin CHECK (cpr_marmin >= 0)");
        DB::statement("ALTER TABLE configuracion_precio
            ADD CONSTRAINT ck_cpr_marmaj CHECK (cpr_marmaj >= 0)");
        DB::statement("ALTER TABLE configuracion_precio
            ADD CONSTRAINT ck_cpr_canminmaj CHECK (cpr_canminmaj > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_precio');
    }
};
