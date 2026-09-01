<?php
// database/migrations/2026_08_30_100002_create_sucursal_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sucursal', function (Blueprint $table) {
            $table->increments('suc_id');
            $table->unsignedInteger('suc_empid');
            $table->unsignedInteger('suc_ciuid');
            $table->string('suc_nombre', 50);
            $table->string('suc_direccion', 100)->nullable();
            $table->string('suc_telef', 20)->nullable();
            $table->string('suc_cel', 20)->nullable();
            $table->string('suc_email', 60)->nullable();
            $table->smallInteger('suc_estado')->default(1);
            $table->timestamp('suc_creado')->useCurrent();

            $table->foreign('suc_empid', 'fk_suc_emp')
                  ->references('emp_id')->on('empresa')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('suc_ciuid', 'fk_suc_ciu')
                  ->references('ciu_id')->on('ciudad')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['suc_empid', 'suc_nombre'], 'uq_suc_emp_nombre');
            $table->index('suc_empid', 'ix_suc_empid');
            $table->index('suc_ciuid', 'ix_suc_ciuid');
        });

        DB::statement("ALTER TABLE sucursal
            ADD CONSTRAINT ck_suc_estado CHECK (suc_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursal');
    }
};