<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de la verificacion del pago por parte del administrador.
 *
 * Hasta ahora pedido_pago solo guardaba lo que el CLIENTE declaro
 * (metodo, monto, comprobante). Estas columnas guardan lo que el
 * ADMINISTRADOR comprobo: numero de transaccion, banco o billetera,
 * fecha real del pago y monto efectivamente verificado.
 *
 * Todas son nullable porque solo se completan al validar el pago: un
 * pago en PENDIENTE no tiene ninguno de estos datos todavia.
 *
 * ppa_montver se guarda aparte de ppa_monto a proposito. ppa_monto es lo
 * que el cliente dijo que iba a pagar y no debe reescribirse nunca; si el
 * comprobante resulta ser por otro importe, la diferencia tiene que
 * quedar visible para la auditoria, no taparse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_pago', function (Blueprint $table) {
            $table->string('ppa_nrotrx', 50)->nullable()->after('ppa_observ');
            $table->string('ppa_banco', 60)->nullable()->after('ppa_nrotrx');
            $table->date('ppa_fechpag')->nullable()->after('ppa_banco');
            $table->decimal('ppa_montver', 14, 2)->nullable()->after('ppa_fechpag');

            $table->unsignedSmallInteger('ppa_mrpid')->nullable()->after('ppa_montver');
            $table->unsignedInteger('ppa_usuver')->nullable()->after('ppa_mrpid');
            $table->timestamp('ppa_fechver')->nullable()->after('ppa_usuver');

            $table->foreign('ppa_mrpid', 'fk_ppa_mrp')
                  ->references('mrp_id')->on('motivo_rechazo_pago')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('ppa_usuver', 'fk_ppa_usuver')
                  ->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');
        });

        DB::statement('ALTER TABLE pedido_pago
            ADD CONSTRAINT ck_ppa_montver CHECK (ppa_montver IS NULL OR ppa_montver > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pedido_pago DROP CONSTRAINT IF EXISTS ck_ppa_montver');

        Schema::table('pedido_pago', function (Blueprint $table) {
            $table->dropForeign('fk_ppa_mrp');
            $table->dropForeign('fk_ppa_usuver');
            $table->dropColumn([
                'ppa_nrotrx', 'ppa_banco', 'ppa_fechpag',
                'ppa_montver', 'ppa_mrpid', 'ppa_usuver', 'ppa_fechver',
            ]);
        });
    }
};