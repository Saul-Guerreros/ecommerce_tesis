<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Amplía Gestión de Precios para soportar markup minorista/mayorista
     * con patrón general + override individual (ver
     * Contexto_Markup_General.md). No se pierde el margen ya cargado
     * manualmente por producto: prd_margen -> prd_marmin (pasa de
     * obligatorio a opcional: NULL = usa configuracion_precio.cpr_marmin).
     */
    public function up(): void
    {
        // 1) prd_margen era obligatorio (ck_prd_margen la validaba como
        //    >= 0 o NULL, pero PrecioRequest la exigía siempre). Se
        //    renombra a prd_marmin y pasa a ser el override minorista.
        DB::statement("ALTER TABLE producto DROP CONSTRAINT IF EXISTS ck_prd_margen");

        Schema::table('producto', function (Blueprint $table) {
            $table->renameColumn('prd_margen', 'prd_marmin');
            $table->renameColumn('prd_prevta', 'prd_pvtmin');
        });

        Schema::table('producto', function (Blueprint $table) {
            // 2) Nuevos overrides individuales (NULL = usa el general)
            $table->decimal('prd_marmaj', 14, 4)->nullable()->after('prd_marmin');
            $table->decimal('prd_canminmaj', 14, 4)->nullable()->after('prd_marmaj');

            // 3) Precio final mayorista calculado. Misma naturaleza que
            //    prd_pvtmin: nunca se edita a mano, lo recalcula
            //    PriceService (a partir del costo promedio + margen
            //    efectivo), igual que ya hace CompraService con
            //    prd_pvtmin al confirmar una compra.
            $table->decimal('prd_pvtmay', 14, 2)->nullable()->after('prd_pvtmin');
        });

        DB::statement("ALTER TABLE producto
            ADD CONSTRAINT ck_prd_marmin CHECK (prd_marmin IS NULL OR prd_marmin >= 0)");
        DB::statement("ALTER TABLE producto
            ADD CONSTRAINT ck_prd_marmaj CHECK (prd_marmaj IS NULL OR prd_marmaj >= 0)");
        DB::statement("ALTER TABLE producto
            ADD CONSTRAINT ck_prd_canminmaj CHECK (prd_canminmaj IS NULL OR prd_canminmaj > 0)");
    }

    public function down(): void
    {
        throw new \RuntimeException('Esta migración no es reversible. Restaurar desde backup si es necesario.');
    }
};
