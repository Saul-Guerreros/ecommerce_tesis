<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('det_nota_credito', function (Blueprint $table) {
            // Mismo criterio que compra_detalle: despor es ayuda de carga,
            // descuento_unitario es la fuente de verdad (se imprime).
            $table->decimal('dnc_despor', 5, 2)->default(0)->after('dnc_cantidad');
            $table->decimal('dnc_descuento_unitario', 14, 2)->default(0)->after('dnc_despor');
        });

        DB::statement("ALTER TABLE det_nota_credito ADD CONSTRAINT ck_dnc_descuento_unitario CHECK (dnc_descuento_unitario >= 0)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE det_nota_credito DROP CONSTRAINT IF EXISTS ck_dnc_descuento_unitario');

        Schema::table('det_nota_credito', function (Blueprint $table) {
            $table->dropColumn(['dnc_despor', 'dnc_descuento_unitario']);
        });
    }
};