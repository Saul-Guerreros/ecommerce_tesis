<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de la declaracion electronica (SIFEN) de cada factura de venta.
 *
 * Por que una tabla aparte y no columnas nuevas en venta_cab:
 *   venta_cab es un documento fiscal que "se emite una vez y no se edita
 *   nunca". El estado electronico SI cambia (PENDIENTE -> ENVIADO, o un
 *   reintento tras un corte de red). Mezclarlos obligaria a modificar la
 *   cabecera de una factura ya emitida.
 *
 * Relacion 1:1 con venta_cab. Una venta tiene como maximo un registro.
 *
 * Que NO guarda: el XML y el PDF. Son archivos, y aca solo queda su ruta
 * (disco privado). La API los conserva cifrados 7 dias como maximo: la
 * copia definitiva es la de este sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_fe', function (Blueprint $table) {
            $table->increments('vfe_id');
            $table->unsignedInteger('vfe_venid');

            // 1 = PENDIENTE, 2 = ENVIADO (aprobado por la DNIT), 3 = RECHAZADO
            $table->smallInteger('vfe_estado')->default(1);

            // --- Lo que devuelve la API ---
            $table->char('vfe_cdc', 44)->nullable();          // codigo de control, 44 digitos
            $table->string('vfe_ambiente', 10)->nullable();   // simulado | test | prod
            $table->string('vfe_codigo', 10)->nullable();     // codigo de respuesta de la DNIT
            $table->text('vfe_mensaje')->nullable();          // ultimo mensaje, para mostrarlo
            $table->text('vfe_aviso')->nullable();            // "aviso" / "prueba" de la API
            $table->text('vfe_qr')->nullable();               // enlace de consulta del QR

            // --- Archivos (copia definitiva en este sistema) ---
            $table->string('vfe_xml_ruta', 255)->nullable();
            $table->string('vfe_pdf_ruta', 255)->nullable();
            // Cuando se confirmo a la API (DELETE .../archivos) que ya estaban guardados.
            $table->timestamp('vfe_confirmado')->nullable();

            // --- Auditoria de envios ---
            $table->smallInteger('vfe_intentos')->default(0);
            $table->timestamp('vfe_ultimo_intento')->nullable();
            $table->timestamp('vfe_aprobado')->nullable();

            $table->timestamp('vfe_creado')->useCurrent();
            $table->timestamp('vfe_actualizado')->useCurrent();

            $table->foreign('vfe_venid', 'fk_vfe_ven')->references('ven_id')->on('venta_cab')
                  ->onUpdate('cascade')->onDelete('restrict');

            // Una venta, un registro electronico. Los NULL no chocan entre si en
            // PostgreSQL, asi que el CDC puede ser unico aunque aun no exista.
            $table->unique('vfe_venid', 'uq_vfe_venta');
            $table->unique('vfe_cdc', 'uq_vfe_cdc');

            // Para la consulta de "que falta declarar".
            $table->index('vfe_estado', 'ix_vfe_estado');
        });

        DB::statement("ALTER TABLE venta_fe ADD CONSTRAINT ck_vfe_estado
            CHECK (vfe_estado IN (1, 2, 3))");

        DB::statement("ALTER TABLE venta_fe ADD CONSTRAINT ck_vfe_cdc
            CHECK (vfe_cdc ~ '^[0-9]{44}$')");

        // Un comprobante aprobado sin CDC ni fecha de aprobacion no es un
        // comprobante aprobado: el CDC es la prueba de que existe ante la DNIT.
        DB::statement("ALTER TABLE venta_fe ADD CONSTRAINT ck_vfe_aprobado
            CHECK (vfe_estado <> 2 OR (vfe_cdc IS NOT NULL AND vfe_aprobado IS NOT NULL))");

        // Solo tiene sentido confirmar archivos de un comprobante aprobado.
        DB::statement("ALTER TABLE venta_fe ADD CONSTRAINT ck_vfe_confirmado
            CHECK (vfe_confirmado IS NULL OR vfe_estado = 2)");

        DB::statement("ALTER TABLE venta_fe ADD CONSTRAINT ck_vfe_intentos
            CHECK (vfe_intentos >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_fe');
    }
};