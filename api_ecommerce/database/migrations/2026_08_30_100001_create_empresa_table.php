<?php
// database/migrations/2026_08_30_100001_create_empresa_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa', function (Blueprint $table) {
            $table->increments('emp_id');
            $table->string('emp_razonsocial', 45);
            $table->string('emp_ruc', 15);
            $table->string('emp_telef', 20)->nullable();
            $table->string('emp_email', 60)->nullable();
            $table->string('emp_actividad', 50)->nullable();
            $table->smallInteger('emp_estado')->default(1);
            $table->timestamp('emp_creado')->useCurrent();

            $table->unique('emp_ruc', 'uq_emp_ruc');
        });

        DB::statement("ALTER TABLE empresa
            ADD CONSTRAINT ck_emp_estado CHECK (emp_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa');
    }
};