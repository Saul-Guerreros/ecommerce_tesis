<?php
// database/migrations/2026_08_31_110002_create_tipo_comprobante_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_comprobante', function (Blueprint $table) {
            $table->increments('tco_id');
            $table->char('tco_codigo', 3);
            $table->string('tco_descri', 45);

            // Si el comprobante necesita timbrado vigente para emitirse.
            $table->smallInteger('tco_reqtim')->default(1);

            // Efecto sobre el stock:
            //  1 = suma (compra)   -1 = resta (venta)   0 = neutro (recibo)
            $table->smallInteger('tco_afecstk')->default(0);

            $table->smallInteger('tco_estado')->default(1);
            $table->timestamp('tco_creado')->useCurrent();

            $table->unique('tco_codigo', 'uq_tco_codigo');
            $table->unique('tco_descri', 'uq_tco_descri');
        });

        DB::statement("ALTER TABLE tipo_comprobante
            ADD CONSTRAINT ck_tco_estado CHECK (tco_estado IN (0, 1))");

        DB::statement("ALTER TABLE tipo_comprobante
            ADD CONSTRAINT ck_tco_reqtim CHECK (tco_reqtim IN (0, 1))");

        DB::statement("ALTER TABLE tipo_comprobante
            ADD CONSTRAINT ck_tco_afecstk CHECK (tco_afecstk IN (-1, 0, 1))");

        DB::statement("ALTER TABLE tipo_comprobante
            ADD CONSTRAINT ck_tco_codigo CHECK (tco_codigo ~ '^[0-9]{3}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_comprobante');
    }
};
