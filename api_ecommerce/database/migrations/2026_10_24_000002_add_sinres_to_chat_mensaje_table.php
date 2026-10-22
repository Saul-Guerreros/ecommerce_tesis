<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * cms_sinres: el turno del asistente no resolvio la consulta.
 * Se marca en el mensaje del asistente cuando:
 *   - el modelo fallo y se mostro el texto de respaldo, o
 *   - se buscaron productos y no se encontro ninguno (o hubo error),
 *     y el turno NO fue derivado (las derivadas se miden aparte).
 *
 * Backfill: solo los respaldos historicos (cms_datos.respaldo = true).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_mensaje', function (Blueprint $table) {
            $table->boolean('cms_sinres')->default(false)->after('cms_latencia_ms');
        });

        DB::statement("
            UPDATE chat_mensaje
               SET cms_sinres = true
             WHERE cms_rol = 'asistente'
               AND (cms_datos ->> 'respaldo') = 'true'
        ");
    }

    public function down(): void
    {
        Schema::table('chat_mensaje', function (Blueprint $table) {
            $table->dropColumn('cms_sinres');
        });
    }
};