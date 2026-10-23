<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 - Ingreso presencial ("mostrador"): segundo canal de entrada al mismo flujo.
 *
 * - sse_canal: WEB (wizard C2 de la tienda) o MOSTRADOR (registrado en la sucursal).
 *   Default WEB: todas las solicitudes existentes quedan como canal web.
 * - sse_usuid: funcionario que registró la solicitud en el mostrador (null en el canal web,
 *   donde el que la crea es el propio cliente).
 * - La sucursal de recepción ya existe: sse_sucid.
 *
 * ck_sse_mostrador fuerza las decisiones del canal presencial: sin costo de retiro,
 * con sucursal, sin dirección (no hay retiro a domicilio) y con funcionario. Que el
 * tipo de entrega sea RETIRO lo valida el service (ten_id no es una constante).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_servicio', function (Blueprint $table) {
            $table->string('sse_canal', 10)->default('WEB');
            $table->unsignedInteger('sse_usuid')->nullable();

            $table->foreign('sse_usuid', 'fk_sse_usu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->index('sse_canal', 'ix_sse_canal');
            $table->index('sse_sucid', 'ix_sse_sucid');
        });

        DB::statement("ALTER TABLE solicitud_servicio ADD CONSTRAINT ck_sse_canal
            CHECK (sse_canal IN ('WEB', 'MOSTRADOR'))");

        DB::statement("ALTER TABLE solicitud_servicio ADD CONSTRAINT ck_sse_mostrador
            CHECK (sse_canal <> 'MOSTRADOR' OR (
                sse_cosret = 0
                AND sse_sucid IS NOT NULL
                AND sse_dirid IS NULL
                AND sse_usuid IS NOT NULL
            ))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE solicitud_servicio DROP CONSTRAINT IF EXISTS ck_sse_mostrador');
        DB::statement('ALTER TABLE solicitud_servicio DROP CONSTRAINT IF EXISTS ck_sse_canal');

        Schema::table('solicitud_servicio', function (Blueprint $table) {
            $table->dropIndex('ix_sse_sucid');
            $table->dropIndex('ix_sse_canal');
            $table->dropForeign('fk_sse_usu');
            $table->dropColumn(['sse_canal', 'sse_usuid']);
        });
    }
};
