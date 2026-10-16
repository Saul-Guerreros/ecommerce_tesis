<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Referencias personales declaradas (hoy son 2, segun el instructivo).
 *
 * Tabla 1:N y no dos pares de columnas en solicitud_credito: si manana
 * el negocio pide una tercera referencia, con este diseno es solo
 * cambiar el minimo del FormArray y la validacion del Request; con
 * columnas fijas seria una migracion sobre una tabla con datos.
 *
 * scf_orden conserva cual fue la Referencia 1 y cual la 2 en el wizard;
 * el UNIQUE evita que un reintento duplique la misma posicion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_credito_referencia', function (Blueprint $table) {
            $table->id('scf_id');
            $table->unsignedBigInteger('scf_scrid');

            $table->unsignedSmallInteger('scf_orden');
            $table->string('scf_nombre', 120);
            $table->string('scf_celula', 20);
            $table->string('scf_relaci', 20);

            $table->foreign('scf_scrid', 'fk_scf_scr')
                  ->references('scr_id')->on('solicitud_credito')
                  ->onUpdate('cascade')->onDelete('cascade');

            $table->unique(['scf_scrid', 'scf_orden'], 'uq_scf_scr_orden');
        });

        DB::statement("ALTER TABLE solicitud_credito_referencia ADD CONSTRAINT ck_scf_relaci
            CHECK (scf_relaci IN ('FAMILIAR','LABORAL','AMISTAD'))");

        DB::statement("ALTER TABLE solicitud_credito_referencia ADD CONSTRAINT ck_scf_orden
            CHECK (scf_orden > 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_credito_referencia');
    }
};