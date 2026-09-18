<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimiento_stock', function (Blueprint $table) {
            $table->unsignedInteger('mst_dncid')->nullable()->after('mst_cmdid');

            $table->foreign('mst_dncid', 'fk_mst_dnc')
                  ->references('dnc_id')->on('det_nota_credito')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement("ALTER TABLE movimiento_stock DROP CONSTRAINT ck_mst_tipo");
        DB::statement("ALTER TABLE movimiento_stock ADD CONSTRAINT ck_mst_tipo CHECK (mst_tipo IN ('E', 'S', 'A'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE movimiento_stock DROP CONSTRAINT ck_mst_tipo");
        DB::statement("ALTER TABLE movimiento_stock ADD CONSTRAINT ck_mst_tipo CHECK (mst_tipo IN ('E', 'S'))");

        Schema::table('movimiento_stock', function (Blueprint $table) {
            $table->dropForeign('fk_mst_dnc');
            $table->dropColumn('mst_dncid');
        });
    }
};