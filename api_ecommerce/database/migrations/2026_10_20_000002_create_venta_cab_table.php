<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cabecera de la factura de venta. Documento fiscal: se emite una vez, no
 * se edita nunca y se anula, no se borra.
 *
 * Todos los campos ven_* de timbrado y de adquirente son SNAPSHOT
 * deliberado. Aunque ven_cliid apunte al cliente, la razon social y el RUC
 * se copian: una factura emitida no puede cambiar porque alguien edito un
 * dato fiscal tres meses despues.
 *
 * Diferencias respecto del SQL de referencia, y por que:
 *   - Se reutiliza sucursal.suc_establ (ya existe) en lugar de crear
 *     suc_establec. No se duplica una columna que ya esta.
 *   - No se incluye ven_aperid: apertura_caja todavia no existe.
 *   - uq_venta_pedido es un indice UNICO PARCIAL (solo facturas vigentes).
 *     Con un UNIQUE total, anular una factura dejaria al pedido sin
 *     posibilidad de reemisión para siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_cab', function (Blueprint $table) {
            $table->increments('ven_id');

            // --- Relaciones ---
            $table->unsignedInteger('ven_timid');              // talonario usado
            $table->unsignedInteger('ven_cajid');              // punto de expedicion
            $table->unsignedInteger('ven_cliid');
            $table->unsignedInteger('ven_usuid');              // usuario que emite
            $table->unsignedInteger('ven_sucid');              // sucursal emisora
            $table->unsignedBigInteger('ven_pedid')->nullable(); // pedido de origen, 1:1

            // --- Identificacion del documento (3-3-7) ---
            $table->char('ven_timbrado', 8);
            $table->date('ven_timb_venc');
            $table->char('ven_establec', 3);
            $table->char('ven_puntoexp', 3);
            $table->integer('ven_numero');
            $table->date('ven_fchaem')->useCurrent();

            // --- Adquirente declarado (viene de pedido_facturacion) ---
            $table->string('ven_razonsocial', 150);
            $table->string('ven_ruc', 15)->nullable();
            $table->string('ven_ci', 15)->nullable();
            $table->string('ven_direccion', 150)->nullable();
            $table->string('ven_telefono', 30)->nullable();
            $table->string('ven_email', 100)->nullable();

            // --- Condicion de venta ---
            $table->smallInteger('ven_condicion')->default(1); // 1=Contado, 2=Credito
            $table->smallInteger('ven_cant_cuotas')->nullable();
            $table->string('ven_nrorem', 15)->nullable();

            // --- Valor de venta (suma vertical del detalle) ---
            $table->decimal('ven_exenta', 14, 2)->default(0);
            $table->decimal('ven_grav5', 14, 2)->default(0);
            $table->decimal('ven_grav10', 14, 2)->default(0);
            $table->decimal('ven_subtotal', 14, 2)->default(0);
            $table->decimal('ven_total', 14, 2)->default(0);

            // --- Liquidacion del IVA (por dentro) ---
            $table->decimal('ven_iva5', 14, 2)->default(0);
            $table->decimal('ven_iva10', 14, 2)->default(0);
            $table->decimal('ven_totiva', 14, 2)->default(0);

            // --- Control ---
            $table->smallInteger('ven_estado')->default(1);    // 1=Emitida, 2=Cobrada, 9=Anulada
            $table->string('ven_obs', 255)->nullable();
            $table->timestamp('ven_creado')->useCurrent();
            $table->timestamp('ven_anulado')->nullable();
            $table->unsignedInteger('ven_anusid')->nullable();
            $table->string('ven_motanu', 255)->nullable();

            $table->foreign('ven_timid', 'fk_ven_tim')->references('tim_id')->on('timbrado')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ven_cajid', 'fk_ven_caj')->references('caj_id')->on('caja')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ven_cliid', 'fk_ven_cli')->references('cli_id')->on('cliente')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ven_usuid', 'fk_ven_usu')->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ven_sucid', 'fk_ven_suc')->references('suc_id')->on('sucursal')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('ven_pedid', 'fk_ven_ped')->references('ped_id')->on('pedido')
                  ->onUpdate('cascade')->onDelete('restrict');

            // Dos facturas no pueden compartir numero dentro del mismo talonario.
            $table->unique(['ven_timid', 'ven_numero'], 'uq_venta_doc');

            $table->index('ven_fchaem', 'ix_ven_fchaem');
            $table->index(['ven_cliid', 'ven_fchaem'], 'ix_ven_cliente');
            $table->index('ven_estado', 'ix_ven_estado');
        });

        // Un pedido tiene como maximo UNA factura vigente.
        DB::statement("CREATE UNIQUE INDEX uq_venta_pedido
            ON venta_cab (ven_pedid) WHERE ven_pedid IS NOT NULL AND ven_estado <> 9");

        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_numero CHECK (ven_numero > 0)");
        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_ident
            CHECK (ven_ruc IS NOT NULL OR ven_ci IS NOT NULL)");
        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_condicion CHECK (ven_condicion IN (1, 2))");
        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_cuotas CHECK (
            (ven_condicion = 1 AND ven_cant_cuotas IS NULL) OR
            (ven_condicion = 2 AND ven_cant_cuotas >= 1))");
        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_subtotal
            CHECK (ven_subtotal = ven_exenta + ven_grav5 + ven_grav10)");
        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_totiva
            CHECK (ven_totiva = ven_iva5 + ven_iva10)");
        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_total
            CHECK (ven_total = ven_subtotal AND ven_total >= 0)");
        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_estado CHECK (ven_estado IN (1, 2, 9))");
        DB::statement("ALTER TABLE venta_cab ADD CONSTRAINT ck_ven_anulado
            CHECK ((ven_estado = 9) = (ven_anulado IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_cab');
    }
};