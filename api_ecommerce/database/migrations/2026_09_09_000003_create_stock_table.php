<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock', function (Blueprint $table) {
            $table->increments('sto_id');
            $table->unsignedInteger('sto_prdid');
            $table->unsignedInteger('sto_depid');
            $table->decimal('sto_cantid', 14, 4)->default(0);
            $table->decimal('sto_reserv', 14, 4)->default(0);
            $table->decimal('sto_cosprm', 14, 4)->default(0);
            $table->timestamp('sto_actual')->useCurrent();

            $table->foreign('sto_prdid', 'fk_sto_prd')
                  ->references('prd_id')->on('producto')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('sto_depid', 'fk_sto_dep')
                  ->references('dpo_id')->on('deposito')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['sto_prdid', 'sto_depid'], 'uq_sto_prd_dep');
        });

        DB::statement("ALTER TABLE stock ADD CONSTRAINT ck_sto_cantid CHECK (sto_cantid >= 0)");
        DB::statement("ALTER TABLE stock ADD CONSTRAINT ck_sto_reserv CHECK (sto_reserv >= 0 AND sto_reserv <= sto_cantid)");
    }

    public function down(): void
    {
        Schema::dropIfExists('stock');
    }
};