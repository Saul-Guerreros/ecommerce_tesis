<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra_cab', function (Blueprint $table) {
            $table->increments('com_id');
            $table->unsignedInteger('com_proid');
            $table->unsignedInteger('com_depid');   // deposito destino de la mercaderia
            $table->unsignedInteger('com_sucid');
            $table->unsignedInteger('com_usuid')->nullable();
            $table->unsignedInteger('com_tcoid');

            // Snapshot del proveedor y del timbrado AL MOMENTO de la compra.
            $table->string('com_proruc', 15);
            $table->string('com_proraz', 150);
            $table->char('com_timbra', 8)->nullable();
            $table->date('com_timbvn')->nullable();

            $table->char('com_nrodoc', 15); // formato 001-001-0000123
            $table->date('com_fchaem');
            $table->string('com_nrorem', 15)->nullable();

            $table->smallInteger('com_condic')->default(0); // 0 contado, 1 credito
            $table->smallInteger('com_plzdia')->nullable();
            $table->smallInteger('com_cuotas')->nullable();
            $table->smallInteger('com_interv')->nullable();
            $table->date('com_fchvto')->nullable();

            $table->decimal('com_exenta', 14, 2)->default(0);
            $table->decimal('com_grav5', 14, 2)->default(0);
            $table->decimal('com_grav10', 14, 2)->default(0);
            $table->decimal('com_iva5', 14, 2)->default(0);
            $table->decimal('com_iva10', 14, 2)->default(0);
            $table->decimal('com_total', 14, 2)->default(0);

            // 1 Confirmada (el flujo real no tiene borrador: Guardar ya
            // impacta stock), 2 Anulada.
            $table->smallInteger('com_estfac')->default(1);

            $table->string('com_obs', 100)->nullable();
            $table->timestamp('com_creado')->useCurrent();
            $table->timestamp('com_fecanu')->nullable();
            $table->unsignedInteger('com_anusid')->nullable();

            $table->foreign('com_proid', 'fk_com_pro')
                  ->references('pro_id')->on('proveedor')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('com_depid', 'fk_com_dep')
                  ->references('dpo_id')->on('deposito')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('com_sucid', 'fk_com_suc')
                  ->references('suc_id')->on('sucursal')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('com_tcoid', 'fk_com_tco')
                  ->references('tco_id')->on('tipo_comprobante')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['com_proid', 'com_timbra', 'com_nrodoc'], 'uq_com_doc_por_proveedor');
        });

        DB::statement("ALTER TABLE compra_cab
            ADD CONSTRAINT ck_com_estfac CHECK (com_estfac IN (1, 2))");
        DB::statement("ALTER TABLE compra_cab
            ADD CONSTRAINT ck_com_condic CHECK (com_condic IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_cab');
    }
};