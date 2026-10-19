<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Presupuesto de la OT, versionado (regla 5: un presupuesto aprobado
 * es inmutable; un cambio genera pse_versio + 1).
 *
 * Ciclo: BORRADOR (técnico) -> EN_REVISION (validación del admin)
 *        -> ENVIADO (cliente) -> APROBADO | RECHAZADO | VENCIDO
 *
 * pse_fecven se fija al ENVIAR (hoy + 7), no al crear: el plazo del cliente
 * corre desde que puede verlo.
 *
 * Columnas agregadas al brief:
 *   pse_obsval: observación del admin al devolver el borrador al técnico.
 *   pse_valusu: admin que validó los precios (control de precios).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuesto_servicio', function (Blueprint $table) {
            $table->id('pse_id');
            $table->string('pse_numero', 20)->nullable();
            $table->unsignedBigInteger('pse_oseid');
            $table->smallInteger('pse_versio')->default(1);
            $table->unsignedSmallInteger('pse_eprid');
            $table->decimal('pse_subtot', 14, 2)->default(0);
            $table->decimal('pse_impues', 14, 2)->default(0);
            $table->decimal('pse_total', 14, 2)->default(0);
            $table->date('pse_fecven')->nullable();
            $table->string('pse_obs', 500)->nullable();
            $table->string('pse_obsval', 255)->nullable();
            $table->string('pse_motrec', 255)->nullable();
            $table->unsignedInteger('pse_usuid');
            $table->unsignedInteger('pse_valusu')->nullable();
            $table->timestamp('pse_enviad')->nullable();
            $table->timestamp('pse_respon')->nullable();
            $table->timestamp('pse_creado')->useCurrent();

            $table->unique('pse_numero', 'uq_pse_numero');
            $table->unique(['pse_oseid', 'pse_versio'], 'uq_pse_ose_versio');

            $table->foreign('pse_oseid', 'fk_pse_ose')->references('ose_id')->on('orden_servicio')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('pse_eprid', 'fk_pse_epr')->references('epr_id')->on('estado_presupuesto')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('pse_usuid', 'fk_pse_usu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('pse_valusu', 'fk_pse_valusu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('pse_eprid', 'ix_pse_eprid');
            $table->index('pse_fecven', 'ix_pse_fecven');
        });

        DB::statement("ALTER TABLE presupuesto_servicio ADD CONSTRAINT ck_pse_montos
            CHECK (pse_subtot >= 0 AND pse_impues >= 0 AND pse_total >= 0)");
        DB::statement("ALTER TABLE presupuesto_servicio ADD CONSTRAINT ck_pse_versio CHECK (pse_versio >= 1)");
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_servicio');
    }
};
