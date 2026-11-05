<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Simplifica la configuración del método Cheque.
 *
 * Quedan sólo dos datos editables por el admin: el titular receptor y el
 * aviso del plazo. El resto pasa a ser fijo:
 *
 *   - Los tipos (a la vista / diferido) se ofrecen siempre.
 *   - La descripción se arma con el titular.
 *   - El mensaje de verificación es un texto fijo.
 *   - El plazo en horas vive escrito dentro del aviso.
 *
 * Antes de borrar las columnas se resuelven los marcadores del aviso
 * ({horas}, {titular}): sin eso, el texto ya guardado quedaría con las
 * llaves visibles en la tienda.
 */
return new class extends Migration
{
    private const AVISO = 'La entrega del cheque perteneciente a esta compra debe realizarse en un plazo máximo de 48 horas. Pasado este tiempo el pedido quedará anulado. El stock y el precio del producto adquirido podrán sufrir variaciones transcurrido este plazo.';

    public function up(): void
    {
        $fila = DB::table('configuracion_tienda')->first();

        if ($fila) {
            $aviso = $fila->ctn_pago_cheque_aviso ?: self::AVISO;

            DB::table('configuracion_tienda')->update([
                'ctn_pago_cheque_aviso' => str_replace(
                    ['{horas}', '{titular}'],
                    [(string) ($fila->ctn_pago_cheque_plazo ?? 48), (string) $fila->ctn_pago_cheque_titular],
                    $aviso,
                ),
            ]);
        }

        Schema::table('configuracion_tienda', function (Blueprint $table) {
            $table->dropColumn([
                'ctn_pago_cheque_vista',
                'ctn_pago_cheque_diferido',
                'ctn_pago_cheque_descri',
                'ctn_pago_cheque_mensaje',
                'ctn_pago_cheque_plazo',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            $table->boolean('ctn_pago_cheque_vista')->default(true);
            $table->boolean('ctn_pago_cheque_diferido')->default(true);
            $table->string('ctn_pago_cheque_descri', 300)->nullable();
            $table->string('ctn_pago_cheque_mensaje', 300)->nullable();
            $table->unsignedSmallInteger('ctn_pago_cheque_plazo')->default(48);
        });
    }
};