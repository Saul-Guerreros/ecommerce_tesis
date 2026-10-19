<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Recepción física del equipo (1:1 con la solicitud).
 * rse_usuid = quien recibió; rse_tecid = técnico asignado (usuario con rol Técnico).
 * rse_clave es TEXT porque se guarda cifrada con el cast 'encrypted' de Laravel
 * (el texto cifrado es mucho más largo que la clave original).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recepcion_servicio', function (Blueprint $table) {
            $table->id('rse_id');
            $table->unsignedBigInteger('rse_sseid');
            $table->unsignedInteger('rse_usuid');
            $table->unsignedInteger('rse_tecid');
            $table->timestamp('rse_fecha')->useCurrent();
            $table->string('rse_estfis', 500);
            $table->text('rse_clave')->nullable();
            $table->string('rse_obs', 500)->nullable();

            $table->unique('rse_sseid', 'uq_rse_sseid');

            $table->foreign('rse_sseid', 'fk_rse_sse')->references('sse_id')->on('solicitud_servicio')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('rse_usuid', 'fk_rse_usu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('rse_tecid', 'fk_rse_tec')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('rse_tecid', 'ix_rse_tecid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepcion_servicio');
    }
};
