<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos fiscales elegidos para facturar una solicitud de servicio.
 * Espejo de pedido_facturacion (que no se toca).
 *
 * Se cargan al pagar: el cliente en la pantalla de pago de la tienda, o el
 * cajero en "Registrar cobro" del panel. Una fila por solicitud: si se
 * vuelven a elegir antes de emitir, se actualiza la misma fila. Una vez
 * emitida la factura ya no cambian (lo controla el service), porque la
 * factura guarda su propia copia en venta_cab.
 *
 * Si nunca se cargan, la factura sale a nombre de la CI de la persona
 * (el mostrador ya exige per_cedula), así que la emisión nunca se bloquea.
 *
 *   sfa_tipdoc  CI | RUC
 *   sfa_dfiid   dato_fiscal de origen (RUC guardado); NULL para la CI o un
 *               RUC de uso único. SET NULL: borrar el dato guardado no borra
 *               lo que se eligió para esta solicitud.
 *   sfa_recibe  CORREO | WHATSAPP: por dónde se le manda la factura.
 *
 * ck_sfa_nrodoc fuerza el formato que exige Facturacion_API (guía 6.7):
 * CI solo dígitos; RUC con guion y dígito verificador. El dígito en sí lo
 * valida el service con DigitoVerificador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicio_facturacion', function (Blueprint $table) {
            $table->id('sfa_id');
            $table->unsignedBigInteger('sfa_sseid');
            $table->string('sfa_tipdoc', 3);                    // CI | RUC
            $table->unsignedBigInteger('sfa_dfiid')->nullable();
            $table->string('sfa_nrodoc', 20);
            $table->string('sfa_raznom', 150);
            $table->string('sfa_telef', 45)->nullable();
            $table->string('sfa_correo', 100)->nullable();
            $table->string('sfa_recibe', 10);                   // CORREO | WHATSAPP
            $table->timestamp('sfa_creado')->useCurrent();
            $table->timestamp('sfa_actual')->nullable();

            $table->foreign('sfa_sseid', 'fk_sfa_sse')->references('sse_id')->on('solicitud_servicio')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('sfa_dfiid', 'fk_sfa_dfi')->references('dfi_id')->on('dato_fiscal')
                  ->onUpdate('cascade')->onDelete('set null');

            $table->unique('sfa_sseid', 'uq_sfa_sse');
        });

        DB::statement("ALTER TABLE servicio_facturacion ADD CONSTRAINT ck_sfa_tipdoc
            CHECK (sfa_tipdoc IN ('CI', 'RUC'))");

        DB::statement("ALTER TABLE servicio_facturacion ADD CONSTRAINT ck_sfa_recibe
            CHECK (sfa_recibe IN ('CORREO', 'WHATSAPP'))");

        DB::statement("ALTER TABLE servicio_facturacion ADD CONSTRAINT ck_sfa_nrodoc CHECK (
            (sfa_tipdoc = 'CI'  AND sfa_nrodoc ~ '^[0-9]{1,15}$') OR
            (sfa_tipdoc = 'RUC' AND sfa_nrodoc ~ '^[0-9]{1,15}-[0-9]$'))");

        // Solo un RUC guardado puede venir de dato_fiscal.
        DB::statement("ALTER TABLE servicio_facturacion ADD CONSTRAINT ck_sfa_dfiid
            CHECK (sfa_dfiid IS NULL OR sfa_tipdoc = 'RUC')");
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_facturacion');
    }
};
