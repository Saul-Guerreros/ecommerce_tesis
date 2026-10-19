<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Caja = punto de expedicion. NO es el cajero ni el dinero: es el catalogo
 * que le da destino a venta_cab.ven_cajid, que es NOT NULL porque toda
 * factura tiene que declarar desde donde se emitio.
 *
 * Se crea solo el catalogo. apertura_caja, cierre_caja, arqueo y
 * recaudacion pertenecen al modulo de Caja y quedan para su propia
 * instruccion; por eso venta_cab todavia NO lleva ven_aperid: una columna
 * sin su FK es una columna que puede guardar cualquier cosa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja', function (Blueprint $table) {
            $table->increments('caj_id');
            $table->unsignedInteger('caj_sucid');
            $table->char('caj_puntexp', 3);          // mismo codigo que timbrado.tim_puntexp
            $table->string('caj_descri', 60);        // 'Tienda Web', 'Caja mostrador'
            $table->smallInteger('caj_tipo')->default(1);   // 1=Fisica, 2=Virtual (web)
            $table->smallInteger('caj_estado')->default(1);
            $table->timestamp('caj_creado')->useCurrent();

            $table->foreign('caj_sucid', 'fk_caj_suc')
                  ->references('suc_id')->on('sucursal')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['caj_sucid', 'caj_puntexp'], 'uq_caj_suc_punto');

            // Par destino de la FK compuesta que usara apertura_caja mas
            // adelante (una apertura solo puede ser de SU sucursal).
            $table->unique(['caj_id', 'caj_sucid'], 'uq_caj_id_suc');
        });

        DB::statement("ALTER TABLE caja ADD CONSTRAINT ck_caj_puntexp CHECK (caj_puntexp ~ '^[0-9]{3}$')");
        DB::statement("ALTER TABLE caja ADD CONSTRAINT ck_caj_tipo   CHECK (caj_tipo IN (1, 2))");
        DB::statement("ALTER TABLE caja ADD CONSTRAINT ck_caj_estado CHECK (caj_estado IN (0, 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('caja');
    }
};