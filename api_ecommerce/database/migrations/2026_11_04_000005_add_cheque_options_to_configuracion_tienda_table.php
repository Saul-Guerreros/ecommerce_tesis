<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opciones del método Cheque que el admin configura en Datos de Pago:
 *
 *   - Tipos permitidos (a la vista / diferido): al menos uno.
 *   - Modalidades de entrega en las que se acepta (retiro / envío): al
 *     menos una.
 *
 * Los valores por defecto reproducen el comportamiento anterior: los dos
 * tipos, y cheque sólo con retiro en sucursal.
 *
 * Las columnas de tipo ya pudieron existir (migración 000001) o haber sido
 * eliminadas (000003), según qué migraciones se hayan ejecutado: por eso
 * cada una se agrega sólo si falta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            if (!Schema::hasColumn('configuracion_tienda', 'ctn_pago_cheque_vista')) {
                $table->boolean('ctn_pago_cheque_vista')->default(true);
            }

            if (!Schema::hasColumn('configuracion_tienda', 'ctn_pago_cheque_diferido')) {
                $table->boolean('ctn_pago_cheque_diferido')->default(true);
            }

            if (!Schema::hasColumn('configuracion_tienda', 'ctn_pago_cheque_retiro')) {
                $table->boolean('ctn_pago_cheque_retiro')->default(true);
            }

            if (!Schema::hasColumn('configuracion_tienda', 'ctn_pago_cheque_envio')) {
                $table->boolean('ctn_pago_cheque_envio')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            foreach ([
                'ctn_pago_cheque_vista',
                'ctn_pago_cheque_diferido',
                'ctn_pago_cheque_retiro',
                'ctn_pago_cheque_envio',
            ] as $columna) {
                if (Schema::hasColumn('configuracion_tienda', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};