<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lead time propio del producto (días que tarda el proveedor en entregar).
 * NULL = usa el valor global configuracion_tienda.ctn_leadtime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->smallInteger('prd_ltdias')->nullable()->after('prd_stkmin');
        });

        DB::statement('ALTER TABLE producto
            ADD CONSTRAINT ck_prd_ltdias CHECK (prd_ltdias IS NULL OR prd_ltdias BETWEEN 1 AND 180)');
    }

    public function down(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->dropColumn('prd_ltdias');
        });
    }
};
