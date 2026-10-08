<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * MÓDULO 6 - ChatBot: la conversación se vincula al USUARIO autenticado
 * (como carrito.car_idusu), no a cliente, que no se obtiene desde el token.
 */
return new class extends Migration
{
    public function up(): void
{
    DB::statement('ALTER TABLE contacto ALTER COLUMN con_valor TYPE VARCHAR(255)');
}

public function down(): void
{
    DB::statement('ALTER TABLE contacto ALTER COLUMN con_valor TYPE VARCHAR(100)');
}
};