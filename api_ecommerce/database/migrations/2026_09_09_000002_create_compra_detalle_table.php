<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra_detalle', function (Blueprint $table) {
            $table->increments('cmd_id');
            $table->unsignedInteger('cmd_comid');
            $table->unsignedInteger('cmd_prdid');
            $table->unsignedInteger('cmd_tivid');
            $table->smallInteger('cmd_nroprd');
            $table->string('cmd_descri', 150); // snapshot del nombre del producto

            // cmd_costo es el INSUMO principal del CPP.
            $table->decimal('cmd_costo', 14, 4);
            $table->decimal('cmd_despor', 5, 2)->default(0);
            $table->decimal('cmd_descto', 14, 2)->default(0);
            $table->decimal('cmd_tasa', 5, 2)->default(0);
            $table->decimal('cmd_gravada', 14, 2)->default(0);
            $table->decimal('cmd_iva', 14, 2)->default(0);

            // Facturada vs recibida pueden diferir. El kardex usa SIEMPRE
            // cmd_canrec, nunca cmd_canfac.
            $table->decimal('cmd_canfac', 14, 4);
            $table->decimal('cmd_canrec', 14, 4);

            $table->foreign('cmd_comid', 'fk_cmd_com')
                  ->references('com_id')->on('compra_cab')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('cmd_prdid', 'fk_cmd_prd')
                  ->references('prd_id')->on('producto')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('cmd_tivid', 'fk_cmd_tiv')
                  ->references('tiv_id')->on('tipo_iva')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement("ALTER TABLE compra_detalle ADD CONSTRAINT ck_cmd_costo CHECK (cmd_costo >= 0)");
        DB::statement("ALTER TABLE compra_detalle ADD CONSTRAINT ck_cmd_canfac CHECK (cmd_canfac > 0)");
        DB::statement("ALTER TABLE compra_detalle ADD CONSTRAINT ck_cmd_canrec CHECK (cmd_canrec >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_detalle');
    }
};