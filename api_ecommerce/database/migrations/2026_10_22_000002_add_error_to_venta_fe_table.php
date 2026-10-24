<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codigo corto del ultimo error de una declaracion (datos_invalidos,
 * sin_respuesta, dnit_sin_respuesta, payload_invalido...).
 *
 * vfe_mensaje es para MOSTRAR al usuario; vfe_error es para que el
 * programa DECIDA: un reintento automatico solo tiene sentido ante un
 * corte de red o un 502/503, nunca ante un dato invalido que hay que
 * corregir a mano. Esa decision se toma en la fase 6.
 *
 * NULL = el ultimo intento no fallo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_fe', function (Blueprint $table) {
            $table->string('vfe_error', 40)->nullable()->after('vfe_estado');
        });
    }

    public function down(): void
    {
        Schema::table('venta_fe', function (Blueprint $table) {
            $table->dropColumn('vfe_error');
        });
    }
};