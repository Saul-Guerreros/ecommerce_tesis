<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Fotos de la solicitud. sad_tipo distingue quién y cuándo:
 *   CLIENTE     -> subidas en el wizard (opcionales)
 *   INGRESO     -> tomadas en la recepción (A2, obligatorias: respaldo legal)
 *   DIAGNOSTICO -> tomadas por el técnico (Paso 3)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_adjunto', function (Blueprint $table) {
            $table->id('sad_id');
            $table->unsignedBigInteger('sad_sseid');
            $table->string('sad_archiv', 255);
            $table->string('sad_nombre', 150)->nullable();   // nombre original del archivo
            $table->string('sad_tipo', 11);
            $table->unsignedInteger('sad_usuid')->nullable();
            $table->timestamp('sad_subido')->useCurrent();

            $table->foreign('sad_sseid', 'fk_sad_sse')->references('sse_id')->on('solicitud_servicio')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('sad_usuid', 'fk_sad_usu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');

            $table->index(['sad_sseid', 'sad_tipo'], 'ix_sad_sse_tipo');
        });

        DB::statement("ALTER TABLE solicitud_adjunto ADD CONSTRAINT ck_sad_tipo
            CHECK (sad_tipo IN ('CLIENTE', 'INGRESO', 'DIAGNOSTICO'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_adjunto');
    }
};
