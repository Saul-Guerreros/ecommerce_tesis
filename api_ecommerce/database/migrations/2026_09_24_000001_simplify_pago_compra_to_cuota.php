<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opcion B (indicacion de tutoria): pago_compra vuelve a ser 1 pago = 1
 * cuota. Se elimina el manejo de caja/cuenta y el pago simultaneo de
 * varias cuotas (eso corresponde a Tesoreria, fuera de alcance). El
 * pago no se edita ni se borra: se anula (pgo_estado) y la cuota
 * vuelve a Pendiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Datos de prueba de la version anterior (1 pago -> N cuotas via
        // detalle_pago_compra) no tienen forma de mapearse a una sola
        // cuota bajo el nuevo esquema 1:1. Se revierten las cuotas
        // afectadas a Pendiente y se vacian las tablas de pago antes de
        // agregar la columna pgo_cuoid NOT NULL.
        if (Schema::hasTable('detalle_pago_compra')) {
            $cuotasAfectadas = DB::table('detalle_pago_compra')->distinct()->pluck('dpc_cuoid');

            if ($cuotasAfectadas->isNotEmpty()) {
                DB::table('cuota_compra')
                    ->whereIn('cuo_id', $cuotasAfectadas)
                    ->update(['cuo_estado' => 0, 'cuo_fecpago' => null, 'cuo_pagusu' => null]);
            }

            DB::table('detalle_pago_compra')->delete();
        }

        DB::table('pago_compra')->delete();

        Schema::dropIfExists('detalle_pago_compra');

        DB::statement('ALTER TABLE pago_compra DROP CONSTRAINT IF EXISTS ck_pgo_total');

        Schema::table('pago_compra', function (Blueprint $table) {
            $table->dropForeign('fk_pgo_pro');
            $table->dropColumn(['pgo_proid', 'pgo_cja', 'pgo_total']);

            $table->unsignedInteger('pgo_cuoid')->after('pgo_id');
            $table->decimal('pgo_importe', 14, 2)->after('pgo_observ');
            $table->smallInteger('pgo_estado')->default(1)->after('pgo_importe'); // 1 Activo, 0 Anulado
            $table->timestamp('pgo_fecanu')->nullable()->after('pgo_creado');
            $table->unsignedInteger('pgo_anusid')->nullable()->after('pgo_fecanu');

            $table->foreign('pgo_cuoid', 'fk_pgo_cuo')
                  ->references('cuo_id')->on('cuota_compra')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement("ALTER TABLE pago_compra ADD CONSTRAINT ck_pgo_estado CHECK (pgo_estado IN (0, 1))");
        DB::statement("ALTER TABLE pago_compra ADD CONSTRAINT ck_pgo_importe CHECK (pgo_importe > 0)");

        DB::statement('CREATE UNIQUE INDEX uq_pgo_cuo_activo ON pago_compra (pgo_cuoid) WHERE pgo_estado = 1');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_pgo_cuo_activo');
        DB::statement('ALTER TABLE pago_compra DROP CONSTRAINT IF EXISTS ck_pgo_estado');
        DB::statement('ALTER TABLE pago_compra DROP CONSTRAINT IF EXISTS ck_pgo_importe');

        Schema::table('pago_compra', function (Blueprint $table) {
            $table->dropForeign('fk_pgo_cuo');
            $table->dropColumn(['pgo_cuoid', 'pgo_importe', 'pgo_estado', 'pgo_fecanu', 'pgo_anusid']);

            $table->unsignedInteger('pgo_proid')->after('pgo_id');
            $table->string('pgo_cja', 60)->nullable()->after('pgo_formapa');
            $table->decimal('pgo_total', 14, 2)->after('pgo_referencia');

            $table->foreign('pgo_proid', 'fk_pgo_pro')
                  ->references('pro_id')->on('proveedor')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement('ALTER TABLE pago_compra ADD CONSTRAINT ck_pgo_total CHECK (pgo_total > 0)');

        Schema::create('detalle_pago_compra', function (Blueprint $table) {
            $table->increments('dpc_id');
            $table->unsignedInteger('dpc_pgoid');
            $table->unsignedInteger('dpc_cuoid');
            $table->decimal('dpc_importe', 14, 2);

            $table->foreign('dpc_pgoid', 'fk_dpc_pgo')
                  ->references('pgo_id')->on('pago_compra')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('dpc_cuoid', 'fk_dpc_cuo')
                  ->references('cuo_id')->on('cuota_compra')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['dpc_pgoid', 'dpc_cuoid'], 'uq_dpc_pgo_cuo');
        });

        DB::statement('ALTER TABLE detalle_pago_compra ADD CONSTRAINT ck_dpc_importe CHECK (dpc_importe > 0)');
    }
};