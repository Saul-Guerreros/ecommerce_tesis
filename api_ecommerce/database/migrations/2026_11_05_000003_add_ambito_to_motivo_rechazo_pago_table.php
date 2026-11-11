<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separa los motivos de rechazo por tipo de evidencia.
 *
 * Sin esto, el select de una transferencia ofrecería "No entregado en
 * plazo" y el de un cheque ofrecería "El comprobante es ilegible": dos
 * opciones imposibles que ensucian los reportes por motivo.
 *
 * AMBOS existe porque hay motivos genuinamente compartidos ("Otro", monto
 * que no coincide): duplicarlos con códigos distintos partiría en dos las
 * estadísticas de un mismo problema de negocio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('motivo_rechazo_pago', function (Blueprint $table) {
            $table->string('mrp_ambito', 11)->default('COMPROBANTE')->after('mrp_descri');
        });

        DB::statement("ALTER TABLE motivo_rechazo_pago
            ADD CONSTRAINT ck_mrp_ambito CHECK (mrp_ambito IN
                ('COMPROBANTE','CHEQUE','AMBOS'))");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE motivo_rechazo_pago DROP CONSTRAINT IF EXISTS ck_mrp_ambito');
        } else {
            try {
                DB::statement('ALTER TABLE motivo_rechazo_pago DROP CHECK ck_mrp_ambito');
            } catch (\Throwable $e) {
            }
        }

        Schema::table('motivo_rechazo_pago', function (Blueprint $table) {
            $table->dropColumn('mrp_ambito');
        });
    }
};