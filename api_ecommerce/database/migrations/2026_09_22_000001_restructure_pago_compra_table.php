<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * pago_compra pasa de ser "1 pago = 1 cuota" a ser la CABECERA de un
 * pago real (una transferencia, un efectivo) que puede aplicarse a
 * varias cuotas, incluso de facturas distintas, del mismo proveedor.
 * El detalle vive en detalle_pago_compra.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE pago_compra DROP CONSTRAINT IF EXISTS ck_pgo_importe');

        Schema::table('pago_compra', function (Blueprint $table) {
            $table->dropForeign('fk_pgo_cuo');
            $table->dropColumn(['pgo_cuoid', 'pgo_importe']);

            $table->unsignedInteger('pgo_proid')->after('pgo_id');
            $table->string('pgo_cja', 60)->nullable()->after('pgo_formapa');
            $table->string('pgo_referencia', 60)->nullable()->after('pgo_cja');
            $table->decimal('pgo_total', 14, 2)->after('pgo_referencia');

            $table->foreign('pgo_proid', 'fk_pgo_pro')
                  ->references('pro_id')->on('proveedor')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement('ALTER TABLE pago_compra ADD CONSTRAINT ck_pgo_total CHECK (pgo_total > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pago_compra DROP CONSTRAINT IF EXISTS ck_pgo_total');

        Schema::table('pago_compra', function (Blueprint $table) {
            $table->dropForeign('fk_pgo_pro');
            $table->dropColumn(['pgo_proid', 'pgo_cja', 'pgo_referencia', 'pgo_total']);

            $table->unsignedInteger('pgo_cuoid')->after('pgo_id');
            $table->decimal('pgo_importe', 14, 2)->after('pgo_cuoid');

            $table->foreign('pgo_cuoid', 'fk_pgo_cuo')
                  ->references('cuo_id')->on('cuota_compra')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement("ALTER TABLE pago_compra ADD CONSTRAINT ck_pgo_importe CHECK (pgo_importe > 0)");
    }
};