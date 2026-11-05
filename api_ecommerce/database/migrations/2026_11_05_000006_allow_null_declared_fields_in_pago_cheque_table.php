<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite vaciar los datos declarados por el cliente.
 *
 * [Descartar cheque] se usa cuando el papel que llega a la sucursal es
 * otro distinto del declarado en el checkout. Si las columnas siguieran
 * NOT NULL habría que escribir cadenas vacías, y una cadena vacía es
 * indistinguible de un dato realmente cargado.
 *
 * pch_tipo queda NOT NULL a propósito: define el flujo (contado o
 * crédito) y siempre hay uno vigente, el declarado o el que corrija el
 * admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_cheque', function (Blueprint $table) {
            $table->string('pch_banco', 80)->nullable()->change();
            $table->string('pch_nrodoc', 20)->nullable()->change();
            $table->string('pch_nombre', 150)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pago_cheque', function (Blueprint $table) {
            $table->string('pch_banco', 80)->nullable(false)->change();
            $table->string('pch_nrodoc', 20)->nullable(false)->change();
            $table->string('pch_nombre', 150)->nullable(false)->change();
        });
    }
};