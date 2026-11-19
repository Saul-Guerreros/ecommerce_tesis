<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuentas a cobrar: una fila por cuota, solo ventas a credito.
 *
 * Cuelga de la FACTURA (cco_venid) y no del pedido ni de la solicitud,
 * porque la deuda nace con el documento fiscal: la solicitud es la
 * OFERTA (puede vencer o rechazarse) y su cronograma, en
 * solicitud_credito_cuota, es un snapshot de lo pactado, no una
 * obligacion cobrable.
 *
 * Las filas se copian de solicitud_credito_cuota al emitir la factura.
 * Desde ahi son independientes: si alguien tocara la solicitud, el
 * cronograma a cobrar no se mueve.
 *
 * PAGOS PARCIALES: no se aceptan. Una cuota se cobra entera o queda
 * pendiente, y por eso cco_saldo solo toma dos valores (cco_monto o 0),
 * garantizado por ck_cco_coherencia. La columna existe igual, y el
 * estado PARCIAL se habilita agregandolo a ck_cco_estado y relajando
 * esa coherencia, si el negocio cambia de criterio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cta_cobrar', function (Blueprint $table) {
            $table->id('cco_id');

            // unsignedInteger y no bigint: venta_cab.ven_id es serial
            // (integer). La FK exige que los tipos sean comparables.
            $table->unsignedInteger('cco_venid');

            $table->unsignedSmallInteger('cco_nrocuo');        // 1, 2, 3...
            $table->date('cco_femisi');                        // = fecha de la factura
            $table->date('cco_vencim');
            $table->decimal('cco_monto', 14, 2);
            $table->decimal('cco_saldo', 14, 2);

            // Que productos componen la cuota. Con planes mixtos el importe
            // baja cuando una linea corta termina de pagarse: sin este texto
            // el admin no puede explicar por que la cuota 4 es menor que la 3.
            $table->string('cco_compos', 300)->nullable();

            $table->string('cco_estado', 10)->default('PENDIENTE');
            $table->date('cco_feccob')->nullable();            // fecha del cobro

            $table->timestamp('cco_creado')->useCurrent();

            $table->foreign('cco_venid', 'fk_cco_venta')
                  ->references('ven_id')->on('venta_cab')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['cco_venid', 'cco_nrocuo'], 'uq_cco_venta_cuota');

            $table->index('cco_vencim', 'ix_cco_vencim');      // orden natural de la cartera
            $table->index('cco_estado', 'ix_cco_estado');
        });

        DB::statement('ALTER TABLE cta_cobrar ADD CONSTRAINT ck_cco_nrocuo CHECK (cco_nrocuo > 0)');
        DB::statement('ALTER TABLE cta_cobrar ADD CONSTRAINT ck_cco_monto  CHECK (cco_monto > 0)');
        DB::statement('ALTER TABLE cta_cobrar ADD CONSTRAINT ck_cco_saldo
            CHECK (cco_saldo >= 0 AND cco_saldo <= cco_monto)');
        DB::statement('ALTER TABLE cta_cobrar ADD CONSTRAINT ck_cco_vencim
            CHECK (cco_vencim >= cco_femisi)');

        // Sin PARCIAL: una cuota se cobra entera o no se cobra.
        DB::statement("ALTER TABLE cta_cobrar ADD CONSTRAINT ck_cco_estado
            CHECK (cco_estado IN ('PENDIENTE', 'COBRADA', 'ANULADA'))");

        // Las tres columnas de estado no pueden contradecirse entre si:
        // cobrada = saldo en cero y con fecha; pendiente = saldo entero y
        // sin fecha. Es la regla de "sin parciales", escrita en la base.
        DB::statement("ALTER TABLE cta_cobrar ADD CONSTRAINT ck_cco_coherencia CHECK (
            (cco_estado = 'COBRADA'   AND cco_saldo = 0          AND cco_feccob IS NOT NULL) OR
            (cco_estado = 'PENDIENTE' AND cco_saldo = cco_monto  AND cco_feccob IS NULL)     OR
            (cco_estado = 'ANULADA'   AND cco_feccob IS NULL)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('cta_cobrar');
    }
};