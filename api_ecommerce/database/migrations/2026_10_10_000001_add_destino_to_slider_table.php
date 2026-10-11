<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza sld_prdid (no se usaba en la tienda) y sld_enlace (texto libre)
 * por un destino tipado: se guarda la REFERENCIA (tipo + id) y la URL se
 * arma al servir la vitrina, así nunca queda un enlace roto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slider', function (Blueprint $table) {
            $table->string('sld_destino_tipo', 20)->nullable();
            $table->unsignedInteger('sld_destino_id')->nullable();
            $table->string('sld_destino_valor', 255)->nullable();
        });

        // Datos existentes: un enlace manual se conserva como "url";
        // si no tenía enlace, se usa el producto que tenía asignado.
        DB::table('slider')
            ->whereNotNull('sld_enlace')
            ->where('sld_enlace', '<>', '')
            ->update(['sld_destino_tipo' => 'url', 'sld_destino_valor' => DB::raw('sld_enlace')]);

        DB::table('slider')
            ->whereNull('sld_destino_tipo')
            ->whereNotNull('sld_prdid')
            ->update(['sld_destino_tipo' => 'producto', 'sld_destino_id' => DB::raw('sld_prdid')]);

        Schema::table('slider', function (Blueprint $table) {
            $table->dropForeign(['sld_prdid']);
            $table->dropColumn(['sld_prdid', 'sld_enlace']);
        });
    }

    public function down(): void
    {
        Schema::table('slider', function (Blueprint $table) {
            $table->unsignedInteger('sld_prdid')->nullable();
            $table->string('sld_enlace', 255)->nullable();
        });

        DB::table('slider')->where('sld_destino_tipo', 'producto')
            ->update(['sld_prdid' => DB::raw('sld_destino_id')]);
        DB::table('slider')->where('sld_destino_tipo', 'url')
            ->update(['sld_enlace' => DB::raw('sld_destino_valor')]);

        Schema::table('slider', function (Blueprint $table) {
            $table->dropColumn(['sld_destino_tipo', 'sld_destino_id', 'sld_destino_valor']);
        });
    }
};