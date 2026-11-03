<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina columnas que quedaron en la base desde una iteracion anterior
 * del ciclo de facturacion (aviso de vencimiento e inutilizacion) y que
 * el codigo actual no usa.
 *
 * Cada borrado esta protegido con hasColumn(): la migracion es valida en
 * un entorno que nunca tuvo esas columnas.
 *
 * ped_rechaz NO se toca: es el contador de rechazos de comprobante y la
 * regla de cancelacion al tercer rechazo (Parte 2) lo necesita.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pedido', 'ped_avisfe')) {
            Schema::table('pedido', function (Blueprint $table) {
                $table->dropColumn('ped_avisfe');
            });
        }

        if (Schema::hasColumn('venta_cab', 'ven_inudnit')) {
            Schema::table('venta_cab', function (Blueprint $table) {
                $table->dropColumn('ven_inudnit');
            });
        }
    }

    public function down(): void
    {
        // Sin reversion: son columnas obsoletas, no se vuelven a crear.
    }
};