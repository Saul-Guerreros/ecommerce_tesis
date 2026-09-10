<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marca', function (Blueprint $table) {
            $table->smallInteger('mar_estado')->default(1)->after('mar_descri');
            $table->timestamp('mar_creado')->useCurrent()->after('mar_estado');
        });

        DB::statement("ALTER TABLE marca
            ADD CONSTRAINT ck_mar_estado CHECK (mar_estado IN (0, 1))");

        // El DER pide mar_descri UNIQUE; el curso la dejo nullable y sin
        // restriccion. Se agrega ahora que el catalogo tiene datos reales
        // para no romper un insert duplicado que ya existiera.
        DB::statement("ALTER TABLE marca
            ADD CONSTRAINT uq_mar_descri UNIQUE (mar_descri)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE marca DROP CONSTRAINT IF EXISTS uq_mar_descri");
        DB::statement("ALTER TABLE marca DROP CONSTRAINT IF EXISTS ck_mar_estado");

        Schema::table('marca', function (Blueprint $table) {
            $table->dropColumn(['mar_estado', 'mar_creado']);
        });
    }
};