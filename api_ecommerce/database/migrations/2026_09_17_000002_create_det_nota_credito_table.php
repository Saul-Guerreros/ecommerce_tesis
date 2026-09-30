<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('det_nota_credito', function (Blueprint $table) {
            $table->increments('dnc_id');
            $table->unsignedInteger('dnc_ncrid');
            $table->unsignedInteger('dnc_cmdid');

            $table->decimal('dnc_cantidad', 14, 4);       // en la unidad facturada de esa línea (igual que cmd_canfac)
            $table->decimal('dnc_base_gravada', 14, 2);
            $table->decimal('dnc_iva', 14, 2)->default(0);
            $table->decimal('dnc_total', 14, 2);

            // Cuanto de dnc_cantidad realmente se pudo descontar del costo
            // de stock (puede ser < dnc_cantidad si parte ya se vendió).
            $table->decimal('dnc_canaju', 14, 4)->default(0);

            $table->foreign('dnc_ncrid', 'fk_dnc_ncr')
                  ->references('ncr_id')->on('nota_credito')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('dnc_cmdid', 'fk_dnc_cmd')
                  ->references('cmd_id')->on('compra_detalle')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement("ALTER TABLE det_nota_credito ADD CONSTRAINT ck_dnc_cantidad CHECK (dnc_cantidad > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('det_nota_credito');
    }
};