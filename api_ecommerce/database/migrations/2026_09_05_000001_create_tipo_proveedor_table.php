<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_proveedor', function (Blueprint $table) {
            $table->increments('tpr_id');
            $table->string('tpr_descri', 45)->unique('uq_tpr_descri');
            $table->smallInteger('tpr_estado')->default(1);
        });

        DB::statement("ALTER TABLE tipo_proveedor
            ADD CONSTRAINT ck_tpr_estado CHECK (tpr_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_proveedor');
    }
};