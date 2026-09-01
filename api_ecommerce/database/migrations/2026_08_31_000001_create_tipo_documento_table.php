<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_documento', function (Blueprint $table) {
            $table->id('tpd_id');
            $table->string('tpd_codigo', 10)->unique('tipo_documento_codigo_uq');
            $table->string('tpd_descri', 50)->unique('tipo_documento_descri_uq');
            $table->smallInteger('tpd_estado')->default(1);
            $table->timestamp('tpd_creado')->useCurrent();
        });

        DB::statement("ALTER TABLE tipo_documento ADD CONSTRAINT tipo_documento_estado_check
                       CHECK (tpd_estado IN (0,1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_documento');
    }
};
