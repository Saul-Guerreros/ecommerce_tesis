<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facturación del servicio técnico: venta_cab pasa a tener dos orígenes
 * posibles, el pedido (ven_pedid) o la solicitud de servicio (ven_sseid).
 *
 * Se reutiliza venta_cab (y con ella venta_detalle, venta_fe, el timbrado,
 * la numeración, el job de declaración y el módulo admin facturas-venta)
 * en lugar de crear tablas espejo: una factura es una factura, venga de
 * donde venga, y el talonario es uno solo.
 *
 * Compatibilidad: ven_sseid es nullable, así que las facturas de pedidos
 * existentes no cambian y el código de pedidos no se entera de la columna.
 *
 *   fk_ven_sse          RESTRICT: un documento fiscal no desaparece con la solicitud.
 *   uq_venta_servicio   una sola factura VIGENTE por solicitud (las anuladas,
 *                       ven_estado = 9, quedan fuera, igual que uq_venta_pedido).
 *   ck_ven_origen       una factura no puede venir de un pedido y de un
 *                       servicio a la vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_cab', function (Blueprint $table) {
            $table->unsignedBigInteger('ven_sseid')->nullable()->after('ven_pedid');

            $table->foreign('ven_sseid', 'fk_ven_sse')->references('sse_id')->on('solicitud_servicio')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement("CREATE UNIQUE INDEX uq_venta_servicio
            ON venta_cab (ven_sseid) WHERE ven_sseid IS NOT NULL AND ven_estado <> 9");

        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_origen
            CHECK (ven_pedid IS NULL OR ven_sseid IS NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE venta_cab DROP CONSTRAINT IF EXISTS ck_ven_origen');
        DB::statement('DROP INDEX IF EXISTS uq_venta_servicio');

        Schema::table('venta_cab', function (Blueprint $table) {
            $table->dropForeign('fk_ven_sse');
            $table->dropColumn('ven_sseid');
        });
    }
};
