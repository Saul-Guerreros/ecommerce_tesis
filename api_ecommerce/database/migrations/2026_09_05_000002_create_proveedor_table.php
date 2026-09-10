<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedor', function (Blueprint $table) {
            $table->increments('pro_id');
            $table->unsignedInteger('pro_tprid');
            $table->unsignedInteger('pro_ciuid');
            $table->string('pro_ruc', 15)->unique('uq_pro_ruc');
            $table->string('pro_razon', 150);
            $table->string('pro_nomfan', 80)->nullable();
            $table->string('pro_direccion', 100)->nullable();
            $table->string('pro_telef', 20)->nullable();
            $table->string('pro_email', 60)->nullable();
            $table->smallInteger('pro_estado')->default(1);
            $table->timestamp('pro_creado')->useCurrent();

            $table->foreign('pro_tprid', 'fk_pro_tpr')
                  ->references('tpr_id')->on('tipo_proveedor')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('pro_ciuid', 'fk_pro_ciu')
                  ->references('ciu_id')->on('ciudad')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('pro_tprid', 'ix_pro_tprid');
            $table->index('pro_ciuid', 'ix_pro_ciuid');
        });

        DB::statement("ALTER TABLE proveedor
            ADD CONSTRAINT ck_pro_estado CHECK (pro_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor');
    }
};