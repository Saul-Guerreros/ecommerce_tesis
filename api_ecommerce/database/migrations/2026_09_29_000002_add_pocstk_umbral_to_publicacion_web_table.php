<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Override opcional, por producto, del umbral general de "poco stock"
 * definido en configuracion_tienda (ctn_pocstk_umbral).
 *
 * Mismo patrón que prd_marmin/prd_marmaj en Gestión de Precios: un valor
 * general que rige por defecto, con excepción individual opcional.
 *
 *  - null   -> usa el umbral general (ConfiguracionTienda::actual()->ctn_pocstk_umbral)
 *  - N >= 1 -> este producto avisa "poco stock" al llegar a N unidades,
 *              sin importar lo que diga la configuración general.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publicacion_web', function (Blueprint $table) {
            $table->unsignedInteger('pub_pocstk_umbral')->nullable()->after('pub_estado');
        });

        DB::statement("ALTER TABLE publicacion_web
            ADD CONSTRAINT ck_pub_pocstk_umbral CHECK (pub_pocstk_umbral IS NULL OR pub_pocstk_umbral >= 1)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE publicacion_web DROP CONSTRAINT IF EXISTS ck_pub_pocstk_umbral');

        Schema::table('publicacion_web', function (Blueprint $table) {
            $table->dropColumn('pub_pocstk_umbral');
        });
    }
};