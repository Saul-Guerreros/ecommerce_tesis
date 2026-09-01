<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persona', function (Blueprint $table) {
            $table->id('per_id');

            $table->foreignId('per_tpdid')
                  ->constrained('tipo_documento', 'tpd_id')
                  ->restrictOnDelete();

            $table->string('per_nombr1', 45);
            $table->string('per_nombr2', 45)->nullable();
            $table->string('per_apell1', 50);
            $table->string('per_apell2', 50)->nullable();
            $table->string('per_cedula', 50);
            $table->string('per_estciv', 50)->nullable();
            $table->date('per_fechna')->nullable();
            $table->smallInteger('per_estado')->default(1);
            $table->timestamp('per_cread')->useCurrent();

            $table->unique(['per_tpdid', 'per_cedula'], 'persona_documento_unique');
            $table->index('per_cedula', 'persona_cedula_idx');
            $table->index('per_apell1', 'persona_apellido_idx');
        });

        DB::statement("ALTER TABLE persona ADD CONSTRAINT persona_estciv_check
                       CHECK (per_estciv IS NULL OR per_estciv IN
                       ('SOLTERO','CASADO','DIVORCIADO','VIUDO','UNION_LIBRE'))");
        DB::statement("ALTER TABLE persona ADD CONSTRAINT persona_estado_check
                       CHECK (per_estado IN (0,1))");
        DB::statement("ALTER TABLE persona ADD CONSTRAINT persona_fechna_check
                       CHECK (per_fechna IS NULL OR per_fechna < CURRENT_DATE)");
    }

    public function down(): void
    {
        Schema::dropIfExists('persona');
    }
};