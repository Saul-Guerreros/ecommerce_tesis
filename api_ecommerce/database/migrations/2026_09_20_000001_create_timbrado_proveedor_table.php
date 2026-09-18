<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Timbrados de los PROVEEDORES (documentos que recibimos).
 * Se separa de 'timbrado' (talonarios propios de la empresa, que emite
 * y numera) porque son entidades distintas: aca no controlamos
 * numeracion, solo validamos que el comprobante recibido sea coherente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timbrado_proveedor', function (Blueprint $table) {
            $table->increments('tpv_id');
            $table->unsignedInteger('tpv_proid');
            $table->unsignedInteger('tpv_tcoid');

            // Un timbrado del proveedor cubre todos sus establecimientos y
            // puntos de expedicion: basta con el numero.
            $table->char('tpv_numero', 8);

            $table->date('tpv_fechin');   // inicio de vigencia
            $table->date('tpv_fechfn');   // fin de vigencia

            $table->smallInteger('tpv_estado')->default(1);
            $table->timestamp('tpv_creado')->useCurrent();

            $table->foreign('tpv_proid', 'fk_tpv_pro')
                  ->references('pro_id')->on('proveedor')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('tpv_tcoid', 'fk_tpv_tco')
                  ->references('tco_id')->on('tipo_comprobante')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(
                ['tpv_proid', 'tpv_tcoid', 'tpv_numero'],
                'uq_tpv_pro_tco_num'
            );

            $table->index(['tpv_proid', 'tpv_tcoid'], 'ix_tpv_pro_tco');
        });

        DB::statement("ALTER TABLE timbrado_proveedor
            ADD CONSTRAINT ck_tpv_estado CHECK (tpv_estado IN (0, 1))");
        DB::statement("ALTER TABLE timbrado_proveedor
            ADD CONSTRAINT ck_tpv_numero CHECK (tpv_numero ~ '^[0-9]{8}$')");
        DB::statement("ALTER TABLE timbrado_proveedor
            ADD CONSTRAINT ck_tpv_vigencia CHECK (tpv_fechfn >= tpv_fechin)");
    }

    public function down(): void
    {
        Schema::dropIfExists('timbrado_proveedor');
    }
};
