<?php

use Database\Seeders\MotivoNotaCreditoSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        (new MotivoNotaCreditoSeeder)->run();   // idempotente

        Schema::table('nota_credito', function (Blueprint $table) {
            $table->unsignedInteger('ncr_mncid')->nullable()->after('ncr_fecha');
        });

        // Backfill: las NC ya cargadas eran todas descuentos comerciales.
        $descId = DB::table('motivo_nota_credito')->where('mnc_codigo', 'DESC')->value('mnc_id');
        DB::table('nota_credito')->whereNull('ncr_mncid')->update(['ncr_mncid' => $descId]);

        DB::statement('ALTER TABLE nota_credito ALTER COLUMN ncr_mncid SET NOT NULL');

        Schema::table('nota_credito', function (Blueprint $table) {
            $table->foreign('ncr_mncid', 'fk_ncr_mnc')
                  ->references('mnc_id')->on('motivo_nota_credito')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('ncr_mncid', 'ix_ncr_mncid');
            $table->dropColumn('ncr_motivo');   // texto libre: reemplazado por la FK
        });
    }

    public function down(): void
    {
        Schema::table('nota_credito', function (Blueprint $table) {
            $table->string('ncr_motivo', 100)->nullable()->after('ncr_fecha');
            $table->dropForeign('fk_ncr_mnc');
            $table->dropIndex('ix_ncr_mncid');
            $table->dropColumn('ncr_mncid');
        });
    }
};