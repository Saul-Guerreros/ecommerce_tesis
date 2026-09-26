<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nota_credito', function (Blueprint $table) {
            $table->unsignedInteger('ncr_tpvid')->nullable()->after('ncr_usuid');
            $table->char('ncr_timbra', 8)->nullable()->after('ncr_tpvid');
            $table->date('ncr_timbvn')->nullable()->after('ncr_timbra');

            $table->foreign('ncr_tpvid', 'fk_ncr_tpv')
                  ->references('tpv_id')->on('timbrado_proveedor')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('ncr_tpvid', 'ix_ncr_tpvid');
        });
    }

    public function down(): void
    {
        Schema::table('nota_credito', function (Blueprint $table) {
            $table->dropForeign('fk_ncr_tpv');
            $table->dropIndex('ix_ncr_tpvid');
            $table->dropColumn(['ncr_tpvid', 'ncr_timbra', 'ncr_timbvn']);
        });
    }
};