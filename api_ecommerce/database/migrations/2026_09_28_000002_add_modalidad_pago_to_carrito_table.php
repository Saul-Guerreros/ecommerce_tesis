<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carrito', function (Blueprint $table) {
            $table->string('car_modpag', 10)->default('contado')->after('car_idpro');
            $table->unsignedSmallInteger('car_numcuo')->nullable()->after('car_modpag');   // número de cuotas elegido
            $table->decimal('car_recargopct', 5, 2)->nullable()->after('car_numcuo');       // snapshot del % aplicado
            $table->decimal('car_montocuo', 14, 2)->nullable()->after('car_recargopct');    // snapshot del monto de cada cuota
        });

        DB::statement("ALTER TABLE carrito ADD CONSTRAINT ck_car_modpag CHECK (car_modpag IN ('contado','credito'))");
        DB::statement("ALTER TABLE carrito ADD CONSTRAINT ck_car_numcuo_credito CHECK (car_modpag = 'contado' OR car_numcuo IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE carrito DROP CONSTRAINT IF EXISTS ck_car_modpag');
        DB::statement('ALTER TABLE carrito DROP CONSTRAINT IF EXISTS ck_car_numcuo_credito');

        Schema::table('carrito', function (Blueprint $table) {
            $table->dropColumn(['car_modpag', 'car_numcuo', 'car_recargopct', 'car_montocuo']);
        });
    }
};