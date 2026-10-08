<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            $table->string('ctn_pago_banco', 80)->nullable();
            $table->string('ctn_pago_banco_titular', 150)->nullable();
            $table->string('ctn_pago_banco_cuenta', 40)->nullable();
            $table->string('ctn_pago_tigo_titular', 150)->nullable();
            $table->string('ctn_pago_tigo_numero', 20)->nullable();
            $table->string('ctn_pago_tigo_referencia', 60)->nullable();
            $table->string('ctn_pago_efectivo_mensaje', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            $table->dropColumn([
                'ctn_pago_banco', 'ctn_pago_banco_titular', 'ctn_pago_banco_cuenta',
                'ctn_pago_tigo_titular', 'ctn_pago_tigo_numero', 'ctn_pago_tigo_referencia',
                'ctn_pago_efectivo_mensaje',
            ]);
        });
    }
};