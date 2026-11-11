<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El mensaje del proceso de verificación del cheque vuelve a ser editable
 * por el admin. La migración 000003 había eliminado la columna al
 * tratarlo como texto fijo.
 *
 * Se comprueba con hasColumn para que funcione en ambos casos: si la
 * 000003 ya corrió, la columna se recrea; si no, ya existe y sólo se
 * completa el texto.
 */
return new class extends Migration
{
    private const MENSAJE = 'El proceso de entrega de los productos iniciará una vez verificado el pago con cheque en la sucursal.';

    public function up(): void
    {
        if (!Schema::hasColumn('configuracion_tienda', 'ctn_pago_cheque_mensaje')) {
            Schema::table('configuracion_tienda', function (Blueprint $table) {
                $table->string('ctn_pago_cheque_mensaje', 300)->nullable();
            });
        }

        // Sólo donde está vacío: si el admin ya cargó un texto propio, no se pisa.
        DB::table('configuracion_tienda')
            ->whereNull('ctn_pago_cheque_mensaje')
            ->update(['ctn_pago_cheque_mensaje' => self::MENSAJE]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('configuracion_tienda', 'ctn_pago_cheque_mensaje')) {
            Schema::table('configuracion_tienda', function (Blueprint $table) {
                $table->dropColumn('ctn_pago_cheque_mensaje');
            });
        }
    }
};