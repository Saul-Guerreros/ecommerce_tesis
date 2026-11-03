<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de la declaracion electronica (SIFEN) de cada Nota de Credito de venta.
 *
 * Espejo exacto de venta_fe, y por el mismo motivo: nota_credito_venta es un
 * documento fiscal que se emite una vez y no se edita nunca; el estado
 * electronico SI cambia (PENDIENTE -> ENVIADO, reintento tras un corte).
 *
 * Relacion 1:1 con nota_credito_venta. La fila se crea DENTRO de la misma
 * transaccion que la NC: por eso la NC "nace electronica" y no existe
 * ninguna NC sin su registro de declaracion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_credito_venta_fe', function (Blueprint $table) {
            $table->increments('nfe_id');
            $table->unsignedInteger('nfe_ncvid');

            // 1 = PENDIENTE, 2 = ENVIADA (aprobada por la DNIT), 3 = RECHAZADA
            $table->smallInteger('nfe_estado')->default(1);
            $table->string('nfe_error', 50)->nullable();

            // --- Lo que devuelve la API ---
            $table->char('nfe_cdc', 44)->nullable();
            $table->string('nfe_ambiente', 10)->nullable();   // simulado | test | prod
            $table->string('nfe_codigo', 10)->nullable();     // codigo de respuesta de la DNIT
            $table->text('nfe_mensaje')->nullable();
            $table->text('nfe_aviso')->nullable();
            $table->text('nfe_qr')->nullable();

            // --- Archivos (copia definitiva en este sistema) ---
            $table->string('nfe_xml_ruta', 255)->nullable();
            $table->string('nfe_pdf_ruta', 255)->nullable();
            $table->timestamp('nfe_confirmado')->nullable();

            // --- Correo que la API intento mandar al aprobar (guia 10.3) ---
            $table->boolean('nfe_correo_enviado')->nullable();
            $table->text('nfe_correo_detalle')->nullable();
            $table->timestamp('nfe_correo_fecha')->nullable();

            // --- Auditoria de envios ---
            $table->smallInteger('nfe_intentos')->default(0);
            $table->timestamp('nfe_ultimo_intento')->nullable();
            $table->timestamp('nfe_aprobado')->nullable();

            $table->timestamp('nfe_creado')->useCurrent();
            $table->timestamp('nfe_actualizado')->useCurrent();

            $table->foreign('nfe_ncvid', 'fk_nfe_ncv')->references('ncv_id')->on('nota_credito_venta')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique('nfe_ncvid', 'uq_nfe_nota');
            $table->unique('nfe_cdc', 'uq_nfe_cdc');
            $table->index('nfe_estado', 'ix_nfe_estado');
        });

        DB::statement("ALTER TABLE nota_credito_venta_fe ADD CONSTRAINT ck_nfe_estado
            CHECK (nfe_estado IN (1, 2, 3))");

        DB::statement("ALTER TABLE nota_credito_venta_fe ADD CONSTRAINT ck_nfe_cdc
            CHECK (nfe_cdc ~ '^[0-9]{44}$')");

        // Una NC aprobada sin CDC ni fecha no es una NC aprobada: el CDC es la
        // prueba de que el documento existe ante la DNIT.
        DB::statement("ALTER TABLE nota_credito_venta_fe ADD CONSTRAINT ck_nfe_aprobado
            CHECK (nfe_estado <> 2 OR (nfe_cdc IS NOT NULL AND nfe_aprobado IS NOT NULL))");

        DB::statement("ALTER TABLE nota_credito_venta_fe ADD CONSTRAINT ck_nfe_confirmado
            CHECK (nfe_confirmado IS NULL OR nfe_estado = 2)");

        DB::statement("ALTER TABLE nota_credito_venta_fe ADD CONSTRAINT ck_nfe_intentos
            CHECK (nfe_intentos >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_credito_venta_fe');
    }
};