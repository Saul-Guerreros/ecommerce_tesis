<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajo', function (Blueprint $table) {
            // Días hábiles estimados: base del plazo del diagnóstico preliminar.
            $table->smallInteger('trb_dias')->default(1);
        });

        DB::statement("ALTER TABLE trabajo ADD CONSTRAINT ck_trb_dias CHECK (trb_dias BETWEEN 1 AND 60)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE trabajo DROP CONSTRAINT IF EXISTS ck_trb_dias');
        Schema::table('trabajo', fn (Blueprint $table) => $table->dropColumn('trb_dias'));
    }
};