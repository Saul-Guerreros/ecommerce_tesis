<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuración de financiación POR PRODUCTO (a diferencia de la vieja
     * plan_cuota, que era un plan único para toda la tienda).
     *
     * precio_financiado y monto_cuota NO se guardan acá: se calculan
     * siempre en el momento a partir de producto.prd_pvtmin. Si se
     * guardaran, cada vez que cambia el precio de venta habría que salir
     * a recalcular y reescribir todas las filas de configuracion_cuota
     * que le pertenecen — con el cálculo en caliente ese problema
     * simplemente no existe.
     */
    public function up(): void
    {
        Schema::create('configuracion_cuota', function (Blueprint $table) {
            $table->id('cfc_id');
            $table->unsignedBigInteger('cfc_prdid');
            $table->unsignedSmallInteger('cfc_cantidad');     // número de cuotas: 3, 6, 12...
            $table->decimal('cfc_recargo_pct', 5, 2);         // 5.00, 10.00, 20.00...
            $table->boolean('cfc_estado')->default(true);
            $table->timestamp('cfc_creado')->useCurrent();

            $table->foreign('cfc_prdid', 'fk_cfc_prd')
                  ->references('prd_id')->on('producto')
                  ->onUpdate('cascade')->onDelete('cascade');

            // No permitir cuotas duplicadas para el mismo producto (punto 8 del instructivo)
            $table->unique(['cfc_prdid', 'cfc_cantidad'], 'uq_cfc_prd_cantidad');
        });

        DB::statement('ALTER TABLE configuracion_cuota ADD CONSTRAINT ck_cfc_cantidad CHECK (cfc_cantidad > 0)');
        DB::statement('ALTER TABLE configuracion_cuota ADD CONSTRAINT ck_cfc_recargo CHECK (cfc_recargo_pct >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_cuota');
    }
};