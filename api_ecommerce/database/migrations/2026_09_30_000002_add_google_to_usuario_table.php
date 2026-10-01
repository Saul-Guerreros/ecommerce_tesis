<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            // "sub" de Google: identificador estable de la cuenta (el email puede cambiar)
            $table->string('usu_gooid', 255)->nullable()->unique();
        });

        // Un email puede superar los 30 caracteres
        DB::statement('ALTER TABLE usuario ALTER COLUMN usu_login TYPE VARCHAR(150)');

        // Quien se registra con Google completa la cedula despues, en su perfil
        DB::statement('ALTER TABLE persona ALTER COLUMN per_cedula DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE persona ALTER COLUMN per_cedula SET NOT NULL');
        DB::statement('ALTER TABLE usuario ALTER COLUMN usu_login TYPE VARCHAR(30)');

        Schema::table('usuario', function (Blueprint $table) {
            $table->dropUnique(['usu_gooid']);
            $table->dropColumn('usu_gooid');
        });
    }
};