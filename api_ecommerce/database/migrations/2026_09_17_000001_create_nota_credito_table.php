<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_credito', function (Blueprint $table) {
            $table->increments('ncr_id');
            $table->unsignedInteger('ncr_comid');
            $table->unsignedInteger('ncr_usuid')->nullable();

            $table->string('ncr_numero', 15);
            $table->date('ncr_fecha');
            $table->string('ncr_motivo', 100);

            // Desglose por tasa (igual criterio que compra_cab), para poder
            // alimentar libro_compra sin tener que reabrir esta tabla despues.
            $table->decimal('ncr_exenta', 14, 2)->default(0);
            $table->decimal('ncr_grav5', 14, 2)->default(0);
            $table->decimal('ncr_grav10', 14, 2)->default(0);
            $table->decimal('ncr_iva5', 14, 2)->default(0);
            $table->decimal('ncr_iva10', 14, 2)->default(0);

            // Totales de conveniencia (evitan recalcular exenta+grav5+grav10
            // e iva5+iva10 en cada pantalla/reporte que solo necesita el total).
            $table->decimal('ncr_gravada', 14, 2)->default(0);
            $table->decimal('ncr_iva', 14, 2)->default(0);
            $table->decimal('ncr_total', 14, 2)->default(0);

            $table->string('ncr_obs', 100)->nullable();
            $table->timestamp('ncr_creado')->useCurrent();

            $table->foreign('ncr_comid', 'fk_ncr_com')
                  ->references('com_id')->on('compra_cab')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['ncr_comid', 'ncr_numero'], 'uq_ncr_doc_por_compra');
        });

        DB::statement("ALTER TABLE nota_credito ADD CONSTRAINT ck_ncr_total CHECK (ncr_total >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_credito');
    }
};