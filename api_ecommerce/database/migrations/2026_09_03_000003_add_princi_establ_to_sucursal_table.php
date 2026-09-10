<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sucursal', function (Blueprint $table) {
            $table->smallInteger('suc_princi')->default(0)->after('suc_nombre');
            $table->char('suc_establ', 3)->nullable()->after('suc_princi');
        });

        DB::statement("ALTER TABLE sucursal
            ADD CONSTRAINT ck_suc_princi CHECK (suc_princi IN (0, 1))");

        // El codigo de establecimiento (usado por Timbrados) es de 3 digitos
        // numericos, igual que en el formato de facturacion paraguayo.
        DB::statement("ALTER TABLE sucursal
            ADD CONSTRAINT ck_suc_establ_formato CHECK (suc_establ ~ '^[0-9]{3}$')");

        // Una misma empresa no puede repetir codigo de establecimiento entre
        // sus sucursales.
        DB::statement("CREATE UNIQUE INDEX uq_suc_emp_establ
            ON sucursal (suc_empid, suc_establ)
            WHERE suc_establ IS NOT NULL");

        // Un solo establecimiento principal activo por empresa, mismo
        // criterio que ya usa deposito.dpo_princi por sucursal.
        DB::statement("CREATE UNIQUE INDEX uq_suc_princi_emp
            ON sucursal (suc_empid)
            WHERE suc_princi = 1 AND suc_estado = 1");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS uq_suc_princi_emp");
        DB::statement("DROP INDEX IF EXISTS uq_suc_emp_establ");
        DB::statement("ALTER TABLE sucursal DROP CONSTRAINT IF EXISTS ck_suc_establ_formato");
        DB::statement("ALTER TABLE sucursal DROP CONSTRAINT IF EXISTS ck_suc_princi");

        Schema::table('sucursal', function (Blueprint $table) {
            $table->dropColumn(['suc_princi', 'suc_establ']);
        });
    }
};