<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ciclo de vida de la solicitud de credito. Mismo patron que
 * estado_pedido / estado_pago: catalogo chico, con codigo estable
 * (el codigo es lo que lee el codigo PHP, nunca el id autonumerico).
 *
 * esc_orden existe para pintar la linea de tiempo en el panel admin
 * sin depender del orden de insercion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estado_solicitud', function (Blueprint $table) {
            $table->smallIncrements('esc_id');
            $table->string('esc_codigo', 20);
            $table->string('esc_descri', 50);
            $table->smallInteger('esc_orden')->default(0);
            $table->smallInteger('esc_estado')->default(1);
            $table->timestamp('esc_creado')->useCurrent();

            $table->unique('esc_codigo', 'uq_esc_codigo');
        });

        DB::statement("ALTER TABLE estado_solicitud
            ADD CONSTRAINT ck_esc_estado CHECK (esc_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('estado_solicitud');
    }
};