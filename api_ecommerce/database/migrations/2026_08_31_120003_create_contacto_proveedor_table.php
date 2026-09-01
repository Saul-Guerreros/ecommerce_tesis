<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacto_proveedor', function (Blueprint $table) {
            $table->increments('cpr_id');
            $table->unsignedInteger('cpr_proid');

            $table->string('cpr_nombre', 60);
            $table->string('cpr_apellido', 60)->nullable();
            $table->string('cpr_cargo', 40)->nullable();
            $table->string('cpr_telef', 20)->nullable();
            $table->string('cpr_email', 80)->nullable();

            $table->smallInteger('cpr_princi')->default(0);
            $table->smallInteger('cpr_estado')->default(1);
            $table->timestamp('cpr_creado')->useCurrent();

            // Al eliminar el proveedor se van sus contactos: no tienen
            // existencia propia fuera de el.
            $table->foreign('cpr_proid', 'fk_cpr_pro')
                  ->references('pro_id')->on('proveedor')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->index('cpr_proid', 'ix_cpr_proid');
        });

        DB::statement("ALTER TABLE contacto_proveedor
            ADD CONSTRAINT ck_cpr_estado CHECK (cpr_estado IN (0, 1))");

        DB::statement("ALTER TABLE contacto_proveedor
            ADD CONSTRAINT ck_cpr_princi CHECK (cpr_princi IN (0, 1))");

        // Un contacto sin telefono ni email no sirve para nada.
        DB::statement("ALTER TABLE contacto_proveedor
            ADD CONSTRAINT ck_cpr_medio
            CHECK (cpr_telef IS NOT NULL OR cpr_email IS NOT NULL)");

        // Un solo contacto principal activo por proveedor.
        DB::statement("CREATE UNIQUE INDEX uq_cpr_princi_pro
            ON contacto_proveedor (cpr_proid)
            WHERE cpr_princi = 1 AND cpr_estado = 1");
    }

    public function down(): void
    {
        Schema::dropIfExists('contacto_proveedor');
    }
};