<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Interruptor de la sección "Métodos de Pago": en qué momento el cheque
 * descuenta stock real.
 *
 *   RECEPCION    -> al confirmar que el papel llegó a la sucursal.
 *   ACREDITACION -> recién cuando el banco acreditó el dinero.
 *
 * Es una columna de configuración y no una constante porque las dos
 * opciones representan criterios de riesgo opuestos y legítimos: RECEPCION
 * permite entregar el producto el mismo día pero expone a la tienda al
 * cheque rebotado; ACREDITACION elimina ese riesgo pero deja la mercadería
 * retenida semanas en un cheque diferido. Cuál conviene depende del
 * cliente, no del software.
 *
 * El valor por defecto es RECEPCION porque es el flujo descrito para
 * entrega en sucursal: se recibe el cheque, se factura y se entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_tienda', function (Blueprint $table) {
            if (!Schema::hasColumn('configuracion_tienda', 'ctn_cheque_stock')) {
                $table->string('ctn_cheque_stock', 12)->default('RECEPCION');
            }
        });

        DB::statement("ALTER TABLE configuracion_tienda
            ADD CONSTRAINT ck_ctn_cheque_stock CHECK (ctn_cheque_stock IN
                ('RECEPCION','ACREDITACION'))");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE configuracion_tienda DROP CONSTRAINT IF EXISTS ck_ctn_cheque_stock');
        } else {
            try {
                DB::statement('ALTER TABLE configuracion_tienda DROP CHECK ck_ctn_cheque_stock');
            } catch (\Throwable $e) {
            }
        }

        Schema::table('configuracion_tienda', function (Blueprint $table) {
            if (Schema::hasColumn('configuracion_tienda', 'ctn_cheque_stock')) {
                $table->dropColumn('ctn_cheque_stock');
            }
        });
    }
};