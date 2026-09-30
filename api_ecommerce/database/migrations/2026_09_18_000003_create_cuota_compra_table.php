<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuota_compra', function (Blueprint $table) {
            $table->increments('cuo_id');
            $table->unsignedInteger('cuo_comid');

            $table->smallInteger('cuo_nrocuo');       // 1, 2, 3...
            $table->date('cuo_venci');
            $table->decimal('cuo_importe', 14, 2);

            $table->smallInteger('cuo_estado')->default(0); // 0 Pendiente, 1 Pagada
            $table->date('cuo_fecpago')->nullable();
            $table->unsignedInteger('cuo_pagusu')->nullable(); // quien la marco pagada

            $table->foreign('cuo_comid', 'fk_cuo_com')
                  ->references('com_id')->on('compra_cab')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->unique(['cuo_comid', 'cuo_nrocuo'], 'uq_cuo_com_nro');
        });

        DB::statement("ALTER TABLE cuota_compra ADD CONSTRAINT ck_cuo_estado CHECK (cuo_estado IN (0, 1))");
        DB::statement("ALTER TABLE cuota_compra ADD CONSTRAINT ck_cuo_importe CHECK (cuo_importe >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('cuota_compra');
    }
};