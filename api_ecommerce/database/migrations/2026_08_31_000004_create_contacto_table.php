<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacto', function (Blueprint $table) {
            $table->id('con_id');

            $table->foreignId('con_perid')
                  ->constrained('persona', 'per_id')
                  ->cascadeOnDelete();

            $table->string('con_tipo', 20);
            $table->string('con_valor', 100);
            $table->smallInteger('con_estado')->default(1);
            $table->timestamp('con_creado')->useCurrent();
            $table->timestamp('con_modific')->nullable();

            $table->unique(['con_perid','con_tipo','con_valor'], 'contacto_persona_valor_unique');
            $table->index('con_valor', 'contacto_valor_idx');
        });

        DB::statement("ALTER TABLE contacto ADD CONSTRAINT contacto_tipo_check
                       CHECK (con_tipo IN ('CELULAR','TELEFONO','WHATSAPP','EMAIL'))");
        DB::statement("ALTER TABLE contacto ADD CONSTRAINT contacto_estado_check
                       CHECK (con_estado IN (0,1))");
        DB::statement("ALTER TABLE contacto ADD CONSTRAINT contacto_valor_check
                       CHECK (LENGTH(TRIM(con_valor)) > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('contacto');
    }
};