<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos del método de pago "Cheque" que el administrador edita desde el
 * panel (Datos de Pago). Siguen la misma convención que los demás
 * métodos: viven en configuracion_tienda con prefijo ctn_pago_, porque
 * son datos de la tienda y no del catálogo metodo_pago.
 *
 * Los textos admiten dos marcadores que el backend reemplaza antes de
 * enviarlos a la tienda: {titular} y {horas}. De esa forma el admin
 * edita el texto una sola vez y el número de horas nunca queda
 * desincronizado con el plazo real configurado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            // Tipos admitidos. Si los dos quedan en false, el método Cheque
            // deja de ofrecerse en el checkout sin tocar metodo_pago.
            $table->boolean('ctn_pago_cheque_vista')->default(true);
            $table->boolean('ctn_pago_cheque_diferido')->default(true);

            $table->string('ctn_pago_cheque_titular', 150)->nullable();
            $table->string('ctn_pago_cheque_descri', 300)->nullable();
            $table->string('ctn_pago_cheque_mensaje', 300)->nullable();

            // Plazo de entrega del cheque en sucursal, en horas.
            $table->unsignedSmallInteger('ctn_pago_cheque_plazo')->default(48);
            $table->string('ctn_pago_cheque_aviso', 400)->nullable();
        });

        // La fila de configuración es un singleton que ya existe: se la
        // completa con los textos del instructivo para que el checkout no
        // muestre campos vacíos antes de que el admin entre a editarlos.
        DB::table('configuracion_tienda')->update([
            'ctn_pago_cheque_titular' => 'Z Informática S.A.',
            'ctn_pago_cheque_descri'  => 'Pagá tu compra online con cheque emitido a nombre de {titular}.',
            'ctn_pago_cheque_mensaje' => 'El proceso de entrega de los productos iniciará una vez verificado el pago con cheque en la sucursal.',
            'ctn_pago_cheque_aviso'   => 'La entrega del cheque perteneciente a esta compra debe realizarse en un plazo máximo de {horas} horas. Pasado este tiempo el pedido quedará anulado. El stock y el precio del producto adquirido podrán sufrir variaciones transcurrido este plazo.',
        ]);
    }

    public function down(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            $table->dropColumn([
                'ctn_pago_cheque_vista',
                'ctn_pago_cheque_diferido',
                'ctn_pago_cheque_titular',
                'ctn_pago_cheque_descri',
                'ctn_pago_cheque_mensaje',
                'ctn_pago_cheque_plazo',
                'ctn_pago_cheque_aviso',
            ]);
        });
    }
};