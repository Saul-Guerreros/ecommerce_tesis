<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajuste_inventario_detalle', function (Blueprint $table) {
            $table->increments('aid_id');
            $table->unsignedInteger('aid_prdid');
            $table->unsignedInteger('aid_ajiid');

            // Snapshot: se recaptura de 'stock' recien al CONFIRMAR (no se
            // confia en el valor cargado en el borrador, puede haber pasado
            // tiempo y haber otros movimientos en el medio).
            $table->decimal('aid_cantsis', 14, 4)->nullable();
            $table->decimal('aid_cantfis', 14, 4);

            // Costo de la entrada, solo se usa si cantfis > cantsis (sobrante).
            // En una salida (merma) no participa del calculo del CPP.
            $table->decimal('aid_costo', 14, 4)->nullable();

            $table->string('aid_obs', 45)->nullable();

            $table->foreign('aid_prdid', 'fk_aid_prd')
                  ->references('prd_id')->on('producto')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('aid_ajiid', 'fk_aid_aji')
                  ->references('aji_id')->on('ajuste_inventario')
                  ->onUpdate('cascade')->onDelete('cascade');
        });

        DB::statement("ALTER TABLE ajuste_inventario_detalle ADD CONSTRAINT ck_aid_cantfis CHECK (aid_cantfis >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('ajuste_inventario_detalle');
    }
};
