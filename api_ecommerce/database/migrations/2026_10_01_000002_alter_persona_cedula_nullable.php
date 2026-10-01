<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La cedula deja de pedirse en el registro publico del e-commerce
 * (AuthController::register). Pasa a completarse despues, desde el
 * perfil del cliente, cuando haga falta (ej. para facturar).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE persona ALTER COLUMN per_cedula DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE persona ALTER COLUMN per_cedula SET NOT NULL');
    }
};