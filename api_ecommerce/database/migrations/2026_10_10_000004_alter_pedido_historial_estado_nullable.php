<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * peh_estid pasa a nullable.
 *
 * La columna guarda "el id del estado alcanzado en el dominio que indica
 * peh_tipo": estado_pedido.est_id cuando peh_tipo = 'PEDIDO' y
 * estado_pago.epa_id cuando peh_tipo = 'PAGO'. Las entradas de tipo
 * 'NOTA' no cambian ningun estado, asi que forzarlas a inventar un id
 * ensuciaria la bitacora.
 *
 * No se le pone FK justamente porque apunta a dos catalogos distintos
 * segun peh_tipo (la alternativa seria una tabla de historial por
 * dominio, innecesaria para el volumen de este sistema).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_historial_estado', function (Blueprint $table) {
            $table->unsignedSmallInteger('peh_estid')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pedido_historial_estado', function (Blueprint $table) {
            $table->unsignedSmallInteger('peh_estid')->nullable(false)->change();
        });
    }
};