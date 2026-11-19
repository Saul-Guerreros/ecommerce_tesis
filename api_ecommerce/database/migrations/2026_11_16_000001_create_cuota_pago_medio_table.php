<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El pago de cuotas pasa a cabecera / medios.
 *
 * Hasta ahora una declaracion era UN metodo: cpa_mpaid vivia en la
 * cabecera y cuota_comprobante colgaba de ella con UNIQUE. Con el pago
 * dividido eso ya no alcanza — dos transferencias en el mismo cobro
 * necesitan dos comprobantes.
 *
 * Reparto nuevo:
 *   cuota_pago         -> el cobro declarado (total y estado general)
 *   cuota_pago_detalle -> que cuotas cubre
 *   cuota_pago_medio   -> con que metodos se cubre (uno o varios)
 *
 * Es el mismo patron de pedido_pago, con la diferencia de que aca la
 * cabecera existe de verdad: un cobro puede cubrir varias cuotas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuota_pago_medio', function (Blueprint $table) {
            $table->id('cpm_id');
            $table->unsignedBigInteger('cpm_cpaid');
            $table->unsignedSmallInteger('cpm_orden');          // 1, 2, 3...

            $table->unsignedSmallInteger('cpm_mpaid');
            $table->decimal('cpm_monto', 14, 2);
            $table->unsignedSmallInteger('cpm_epaid');          // estado_pago

            $table->string('cpm_nrotrx', 50)->nullable();
            $table->string('cpm_banco', 60)->nullable();
            $table->date('cpm_fechpag')->nullable();

            // Verificacion del admin, por medio: con pago dividido se
            // rechaza un comprobante sin tocar el otro.
            $table->string('cpm_observ', 255)->nullable();
            $table->unsignedSmallInteger('cpm_mrpid')->nullable();
            $table->unsignedInteger('cpm_usuver')->nullable();
            $table->timestamp('cpm_fechver')->nullable();

            $table->timestamp('cpm_creado')->useCurrent();

            $table->foreign('cpm_cpaid', 'fk_cpm_cpa')
                  ->references('cpa_id')->on('cuota_pago')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('cpm_mpaid', 'fk_cpm_mpa')
                  ->references('mpa_id')->on('metodo_pago')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('cpm_epaid', 'fk_cpm_epa')
                  ->references('epa_id')->on('estado_pago')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('cpm_mrpid', 'fk_cpm_mrp')
                  ->references('mrp_id')->on('motivo_rechazo_pago')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('cpm_usuver', 'fk_cpm_usuver')
                  ->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');

            $table->unique(['cpm_cpaid', 'cpm_orden'], 'uq_cpm_pago_orden');
            $table->index('cpm_epaid', 'ix_cpm_estado');
        });

        DB::statement('ALTER TABLE cuota_pago_medio ADD CONSTRAINT ck_cpm_monto CHECK (cpm_monto > 0)');

        // Las declaraciones que ya existen pasan a tener un medio.
        DB::statement('
            INSERT INTO cuota_pago_medio (
                cpm_cpaid, cpm_orden, cpm_mpaid, cpm_monto, cpm_epaid,
                cpm_nrotrx, cpm_banco, cpm_fechpag,
                cpm_observ, cpm_mrpid, cpm_usuver, cpm_fechver, cpm_creado
            )
            SELECT cpa_id, 1, cpa_mpaid, cpa_monto, cpa_epaid,
                   cpa_nrotrx, cpa_banco, cpa_fechpag,
                   cpa_observ, cpa_mrpid, cpa_usuver, cpa_fechver, cpa_creado
              FROM cuota_pago
        ');

        // El comprobante deja de colgar de la declaracion y pasa al medio.
        Schema::table('cuota_comprobante', function (Blueprint $table) {
            $table->unsignedBigInteger('ccm_cpmid')->nullable()->after('ccm_id');
        });

        DB::statement('
            UPDATE cuota_comprobante
               SET ccm_cpmid = (SELECT cpm_id FROM cuota_pago_medio
                                 WHERE cpm_cpaid = cuota_comprobante.ccm_cpaid
                                 LIMIT 1)
        ');

        // No puede quedar ninguno (la FK anterior lo impedia), pero si la
        // base trae basura mejor que falle aca y no con la NOT NULL.
        DB::statement('DELETE FROM cuota_comprobante WHERE ccm_cpmid IS NULL');
        DB::statement('ALTER TABLE cuota_comprobante ALTER COLUMN ccm_cpmid SET NOT NULL');

        Schema::table('cuota_comprobante', function (Blueprint $table) {
            $table->foreign('ccm_cpmid', 'fk_ccm_cpm')
                  ->references('cpm_id')->on('cuota_pago_medio')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->unique('ccm_cpmid', 'uq_ccm_medio');

            $table->dropForeign('fk_ccm_cpa');
            $table->dropUnique('uq_ccm_pago');
            $table->dropColumn('ccm_cpaid');
        });

        // El metodo ya no vive en la cabecera. Se deja la columna (el pago
        // unico la sigue escribiendo, y el admin lee de ahi) pero deja de
        // ser obligatoria: con pago dividido no hay "un" metodo.
        DB::statement('ALTER TABLE cuota_pago ALTER COLUMN cpa_mpaid DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('cuota_comprobante', function (Blueprint $table) {
            $table->unsignedBigInteger('ccm_cpaid')->nullable()->after('ccm_id');
        });

        DB::statement('
            UPDATE cuota_comprobante
               SET ccm_cpaid = (SELECT cpm_cpaid FROM cuota_pago_medio
                                 WHERE cpm_id = cuota_comprobante.ccm_cpmid)
        ');

        DB::statement('DELETE FROM cuota_comprobante WHERE ccm_cpaid IS NULL');
        DB::statement('ALTER TABLE cuota_comprobante ALTER COLUMN ccm_cpaid SET NOT NULL');

        Schema::table('cuota_comprobante', function (Blueprint $table) {
            $table->foreign('ccm_cpaid', 'fk_ccm_cpa')
                  ->references('cpa_id')->on('cuota_pago')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->unique('ccm_cpaid', 'uq_ccm_pago');

            $table->dropForeign('fk_ccm_cpm');
            $table->dropUnique('uq_ccm_medio');
            $table->dropColumn('ccm_cpmid');
        });

        Schema::dropIfExists('cuota_pago_medio');

        DB::statement('ALTER TABLE cuota_pago ALTER COLUMN cpa_mpaid SET NOT NULL');
    }
};