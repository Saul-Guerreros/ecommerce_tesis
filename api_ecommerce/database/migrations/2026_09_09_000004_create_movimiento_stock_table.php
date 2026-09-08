<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimiento_stock', function (Blueprint $table) {
            $table->bigIncrements('mst_id');
            $table->unsignedBigInteger('mst_aidid')->nullable(); // ajuste_inventario_detalle (futuro)
            $table->unsignedInteger('mst_cmdid')->nullable();    // compra_detalle que origino el movimiento
            $table->unsignedInteger('mst_usuid')->nullable();
            $table->unsignedBigInteger('mst_revid')->nullable(); // si es reversa, apunta al movimiento original
            $table->timestamp('mst_fecha');
            $table->char('mst_tipo', 1);                         // 'E' Entrada / 'S' Salida
            $table->decimal('mst_cantid', 14, 4);
            $table->decimal('mst_cosuni', 14, 4);
            $table->decimal('mst_salcan', 14, 4);
            $table->decimal('mst_salcos', 14, 4);
            $table->string('mst_obs', 45)->nullable();
            $table->timestamp('mst_creado')->useCurrent();
            $table->unsignedInteger('mst_stprid');
            $table->unsignedInteger('mst_stdeid');

            $table->foreign('mst_cmdid', 'fk_mst_cmd')
                  ->references('cmd_id')->on('compra_detalle')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('mst_revid', 'fk_mst_rev')
                  ->references('mst_id')->on('movimiento_stock')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('mst_stprid', 'fk_mst_prd')
                  ->references('prd_id')->on('producto')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('mst_stdeid', 'fk_mst_dep')
                  ->references('dpo_id')->on('deposito')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index(['mst_stprid', 'mst_stdeid', 'mst_id'], 'ix_mst_prd_dep_orden');
        });

        DB::statement("ALTER TABLE movimiento_stock ADD CONSTRAINT ck_mst_tipo CHECK (mst_tipo IN ('E', 'S'))");
        DB::statement("ALTER TABLE movimiento_stock ADD CONSTRAINT ck_mst_cantid CHECK (mst_cantid > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('movimiento_stock');
    }
};