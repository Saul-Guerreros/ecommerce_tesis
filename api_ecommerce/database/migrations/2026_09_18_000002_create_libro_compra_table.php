<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('libro_compra', function (Blueprint $table) {
            $table->increments('lcm_id');
            $table->char('lcm_tipo', 2); // 'FC' Factura de Compra / 'NC' Nota de Credito
            $table->unsignedInteger('lcm_comid')->nullable();
            $table->unsignedInteger('lcm_ncrid')->nullable();
            $table->smallInteger('lcm_estado')->default(1); // 1 Vigente, 2 Anulado

            $table->date('lcm_fecha');
            $table->string('lcm_nrodoc', 15);
            $table->string('lcm_proraz', 150);
            $table->string('lcm_proruc', 15);

            $table->decimal('lcm_exenta', 14, 2)->default(0);
            $table->decimal('lcm_grav5', 14, 2)->default(0);
            $table->decimal('lcm_grav10', 14, 2)->default(0);
            $table->decimal('lcm_iva5', 14, 2)->default(0);
            $table->decimal('lcm_iva10', 14, 2)->default(0);
            $table->decimal('lcm_total', 14, 2)->default(0);

            $table->timestamp('lcm_creado')->useCurrent();

            $table->foreign('lcm_comid', 'fk_lcm_com')
                  ->references('com_id')->on('compra_cab')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('lcm_ncrid', 'fk_lcm_ncr')
                  ->references('ncr_id')->on('nota_credito')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement("ALTER TABLE libro_compra ADD CONSTRAINT ck_lcm_tipo CHECK (lcm_tipo IN ('FC', 'NC'))");
        DB::statement("ALTER TABLE libro_compra ADD CONSTRAINT ck_lcm_estado CHECK (lcm_estado IN (1, 2))");
        DB::statement("
            ALTER TABLE libro_compra ADD CONSTRAINT ck_lcm_arco_exclusivo CHECK (
                (lcm_tipo = 'FC' AND lcm_comid IS NOT NULL AND lcm_ncrid IS NULL) OR
                (lcm_tipo = 'NC' AND lcm_ncrid IS NOT NULL AND lcm_comid IS NULL)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('libro_compra');
    }
};