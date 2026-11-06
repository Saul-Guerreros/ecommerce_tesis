<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vinculacion_cuenta', function (Blueprint $table) {
            $table->id('vin_id');

            $table->foreignId('vin_perid')
                  ->constrained('persona', 'per_id')
                  ->cascadeOnDelete();

            $table->text('vin_codigo');                       // cifrado (cast 'encrypted' del modelo)
            $table->timestamp('vin_fecexp');
            $table->smallInteger('vin_intentos')->default(0);
            $table->string('vin_estado', 10)->default('VIGENTE');
            $table->timestamp('vin_fecuso')->nullable();
            $table->timestamp('vin_creado')->useCurrent();

            $table->index(['vin_perid', 'vin_estado'], 'vinculacion_persona_estado_idx');
        });

        DB::statement("ALTER TABLE vinculacion_cuenta ADD CONSTRAINT vinculacion_estado_check
                       CHECK (vin_estado IN ('VIGENTE','USADO','ANULADO'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('vinculacion_cuenta');
    }
};