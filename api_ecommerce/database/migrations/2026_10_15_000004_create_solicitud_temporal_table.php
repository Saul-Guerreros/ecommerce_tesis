<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Borrador del wizard de Solicitud de Credito: una fila por usuario,
 * sobrescrita en cada paso. Mismo criterio que pedido_temporal.
 *
 * Por que un borrador en BD y no solo estado en Angular:
 *  - solicitud_credito no puede nacer antes del Paso 4: le faltan los
 *    totales definitivos, el numero y la declaracion de veracidad.
 *  - Si el cliente recarga la pagina o vuelve al dia siguiente, no
 *    pierde lo que ya cargo.
 *  - El boton [Editar] de la pantalla de confirmacion necesita releer
 *    un bloque puntual; con jsonb por bloque, eso es una sola lectura.
 *
 * jsonb y no columnas: el borrador es descartable, no se consulta ni
 * se reporta. Las columnas tipadas viven en solicitud_credito, que es
 * la que si queda como declaracion formal del cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_temporal', function (Blueprint $table) {
            $table->id('stm_id');
            $table->unsignedBigInteger('stm_usuid');

            $table->smallInteger('stm_paso')->default(1);
            $table->jsonb('stm_person')->nullable();   // Paso 1: identificacion + domicilio + situacion
            $table->jsonb('stm_labora')->nullable();   // Paso 2: lugar de trabajo + situacion economica
            $table->jsonb('stm_refere')->nullable();   // Paso 3: arreglo de referencias

            $table->timestamp('stm_creado')->nullable();
            $table->timestamp('stm_actual')->nullable();

            $table->foreign('stm_usuid', 'fk_stm_usu')
                  ->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->unique('stm_usuid', 'uq_stm_usuid');
        });

        DB::statement('ALTER TABLE solicitud_temporal
            ADD CONSTRAINT ck_stm_paso CHECK (stm_paso BETWEEN 1 AND 4)');
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_temporal');
    }
};