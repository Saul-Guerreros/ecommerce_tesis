<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa el snapshot fiscal de venta_cab con lo que le faltaba a la
 * cabecera de la factura:
 *
 *   - ven_timb_inicio : inicio de vigencia del timbrado (el vencimiento ya
 *                       estaba en ven_timb_venc).
 *   - ven_emp_ruc     : RUC de la empresa que vende.
 *   - ven_emp_razon   : razon social de la empresa que vende. Un RUC solo,
 *                       sin nombre, no identifica al emisor ante el cliente.
 *
 * Son copias, no referencias: si manana cambia el RUC o la razon social de
 * la empresa, las facturas ya emitidas siguen diciendo lo que decian.
 *
 * Se agregan como NULLABLE, se rellenan las facturas que ya existan desde
 * su timbrado y su empresa, y recien entonces pasan a NOT NULL. Asi la
 * migracion funciona tanto con la tabla vacia como con facturas de prueba.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_cab', function (Blueprint $table) {
            $table->date('ven_timb_inicio')->nullable()->after('ven_timbrado');
            $table->string('ven_emp_ruc', 15)->nullable()->after('ven_timb_venc');
            $table->string('ven_emp_razon', 45)->nullable()->after('ven_emp_ruc');
        });

        DB::statement("
            UPDATE venta_cab v
               SET ven_timb_inicio = t.tim_fechin,
                   ven_emp_ruc     = e.emp_ruc,
                   ven_emp_razon   = e.emp_razonsocial
              FROM timbrado t
              JOIN empresa e ON e.emp_id = t.tim_empid
             WHERE t.tim_id = v.ven_timid
        ");

        DB::statement("ALTER TABLE venta_cab ALTER COLUMN ven_timb_inicio SET NOT NULL");
        DB::statement("ALTER TABLE venta_cab ALTER COLUMN ven_emp_ruc SET NOT NULL");
        DB::statement("ALTER TABLE venta_cab ALTER COLUMN ven_emp_razon SET NOT NULL");

        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_timb_vigencia
            CHECK (ven_timb_venc >= ven_timb_inicio)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE venta_cab DROP CONSTRAINT IF EXISTS ck_ven_timb_vigencia");

        Schema::table('venta_cab', function (Blueprint $table) {
            $table->dropColumn(['ven_timb_inicio', 'ven_emp_ruc', 'ven_emp_razon']);
        });
    }
};