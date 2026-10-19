<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Días laborables de cada sucursal, para calcular el plazo de retiro en
 * días hábiles.
 *
 * No se deriva de suc_horario a propósito: esa columna es texto libre
 * ("Lunes a viernes de 8 a 18, sábados hasta 13") pensada para mostrarle
 * al cliente, no para que el sistema la interprete. Parsearla sería
 * frágil y, peor, fallaría en silencio: un admin que escriba el horario
 * de otra forma rompería el cálculo del plazo sin que nadie lo note.
 *
 * Formato: 7 caracteres '1'/'0', de lunes a domingo, alineado con
 * Carbon::dayOfWeekIso (1 = lunes ... 7 = domingo). Se eligió una máscara
 * de texto y no una tabla aparte porque el dato es de tamaño fijo, se lee
 * siempre entero y nunca se consulta por día suelto; una tabla hija sólo
 * agregaría un JOIN a cada cálculo.
 *
 * El default '1111110' (lunes a sábado) es el horario real de
 * Z Informática, así que las sucursales ya cargadas quedan correctas sin
 * tener que editarlas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sucursal', function (Blueprint $table) {
            $table->string('suc_diaslab', 7)
                ->default('1111110')
                ->after('suc_horario');
        });
    }

    public function down(): void
    {
        Schema::table('sucursal', function (Blueprint $table) {
            $table->dropColumn('suc_diaslab');
        });
    }
};