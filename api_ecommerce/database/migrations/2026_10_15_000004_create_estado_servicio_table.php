<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Eje 1: ciclo técnico de la solicitud (solicitud_servicio.sse_etsid).
 *
 * ets_visible: estado que ve el CLIENTE (la tienda nunca muestra el código interno).
 * ets_orden:   orden del ciclo, para listados y para validar que no se retroceda.
 * Catálogo de sistema: lo busca el código por ets_codigo, no tiene CRUD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estado_servicio', function (Blueprint $table) {
            $table->smallIncrements('ets_id');
            $table->string('ets_codigo', 20);
            $table->string('ets_descri', 50);
            $table->string('ets_visible', 20);
            $table->smallInteger('ets_orden');
            $table->smallInteger('ets_estado')->default(1);
            $table->timestamp('ets_creado')->useCurrent();

            $table->unique('ets_codigo', 'uq_ets_codigo');
        });

        DB::statement("ALTER TABLE estado_servicio ADD CONSTRAINT ck_ets_estado CHECK (ets_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('estado_servicio');
    }
};
