<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cabecera de la Solicitud de Credito. Nace recien en el Paso 4
 * (GENERAR SOLICITUD): SolicitudService lee solicitud_temporal + el
 * carrito a credito y crea esta fila, su detalle y sus referencias en
 * una sola transaccion.
 *
 * TODOS los datos personales y laborales se guardan como SNAPSHOT y no
 * como FK a persona/direccion/contacto. Una solicitud de credito es una
 * declaracion del cliente en un momento dado: si manana se muda o cambia
 * de trabajo, la solicitud que el administrador evaluo tiene que seguir
 * diciendo lo que decia. Mismo criterio ya aplicado en
 * pedido_facturacion.
 *
 * Excepcion deliberada: scr_ciuid y scr_proid si son FK. Son catalogos
 * controlados que no se editan ni se borran (ciudad y profesion estan
 * protegidas por RESTRICT), y el panel admin necesita filtrar y agrupar
 * solicitudes por ciudad y por profesion -- cosa que con texto libre no
 * se puede hacer de forma confiable.
 *
 * Nombres y apellidos van en dos campos y no en cuatro (como
 * persona.per_nombr1/2 y per_apell1/2) porque el formulario del wizard
 * pide "Nombres" y "Apellidos" en un solo campo cada uno. Al precargar,
 * el backend concatena; al guardar, no intenta volver a partirlos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_credito', function (Blueprint $table) {
            $table->id('scr_id');
            $table->string('scr_numero', 20)->nullable();   // SC-000127, se asigna tras el INSERT

            $table->unsignedInteger('scr_cliid');
            $table->unsignedBigInteger('scr_usuid');        // usuario que genero la solicitud
            $table->unsignedSmallInteger('scr_escid');

            // ---------- Paso 1/3: Identificacion declarada ----------
            $table->string('scr_cedula', 50);
            $table->string('scr_nombre', 95);
            $table->string('scr_apelli', 100);
            $table->date('scr_fechna');
            $table->string('scr_celula', 20);
            $table->string('scr_email', 100);

            // ---------- Paso 2/3: Domicilio declarado ----------
            // El departamento no se guarda: sale de ciudad.ciu_depid.
            $table->unsignedInteger('scr_ciuid');
            $table->string('scr_barrio', 60);
            $table->string('scr_calle', 100);
            $table->string('scr_nrocasa', 45)->nullable();
            $table->string('scr_referen', 250)->nullable();

            // ---------- Paso 3/3: Situacion personal ----------
            $table->string('scr_estciv', 20);
            $table->unsignedInteger('scr_proid');
            $table->string('scr_tipviv', 20);
            // Solo se cargan cuando la vivienda es PROPIA (regla reforzada
            // con ck_scr_vivienda, no solo con el *ngIf de Angular).
            $table->string('scr_sertit', 150)->nullable();  // ANDE/ESSAP a nombre de
            $table->string('scr_relsti', 100)->nullable();  // relacion con el titular

            // ---------- Datos laborales ----------
            $table->string('scr_empres', 150);
            $table->string('scr_dirlab', 200);
            $table->string('scr_tellab', 20);
            $table->string('scr_cargo', 100);
            $table->decimal('scr_ingres', 14, 2);
            $table->date('scr_fecing');

            // ---------- Credito solicitado (snapshot del carrito) ----------
            // La antiguedad laboral NO se guarda: se calcula desde
            // scr_fecing. Guardarla seria almacenar un dato que envejece
            // mal (hoy "2 anios 4 meses", en seis meses ya es falso).
            $table->decimal('scr_toncon', 14, 2);           // precio al contado
            $table->decimal('scr_recfin', 14, 2);           // recargo por financiacion
            $table->decimal('scr_total', 14, 2);            // total a pagar
            $table->decimal('scr_impues', 14, 2)->default(0); // IVA contenido en el total
            $table->unsignedSmallInteger('scr_plazo');      // plazo en meses (el plan mas largo)
            $table->decimal('scr_cuomen', 14, 2);           // cuota del primer tramo

            $table->boolean('scr_declar')->default(false);  // "Declaro que los datos son veraces"
            $table->string('scr_observ', 500)->nullable();  // uso del panel admin (resolucion)

            $table->timestamp('scr_creado')->useCurrent();
            $table->timestamp('scr_actual')->nullable();

            $table->foreign('scr_cliid', 'fk_scr_cli')
                  ->references('cli_id')->on('cliente')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('scr_usuid', 'fk_scr_usu')
                  ->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('scr_escid', 'fk_scr_esc')
                  ->references('esc_id')->on('estado_solicitud')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('scr_ciuid', 'fk_scr_ciu')
                  ->references('ciu_id')->on('ciudad')
                  ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('scr_proid', 'fk_scr_pro')
                  ->references('pro_id')->on('profesion')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->unique('scr_numero', 'uq_scr_numero');
            $table->index(['scr_cliid', 'scr_creado'], 'ix_scr_cliente');
            $table->index('scr_escid', 'ix_scr_estado');
        });

        DB::statement("ALTER TABLE solicitud_credito ADD CONSTRAINT ck_scr_estciv
            CHECK (scr_estciv IN ('SOLTERO','CASADO','DIVORCIADO','VIUDO','UNION_LIBRE'))");

        DB::statement("ALTER TABLE solicitud_credito ADD CONSTRAINT ck_scr_tipviv
            CHECK (scr_tipviv IN ('PROPIA','ALQUILER','FAMILIAR'))");

        // Los datos del servicio ANDE/ESSAP solo tienen sentido con casa
        // propia. Sin esta restriccion, un POST armado a mano podria dejar
        // una solicitud de alquiler con titular de servicio cargado.
        DB::statement("ALTER TABLE solicitud_credito ADD CONSTRAINT ck_scr_vivienda
            CHECK (scr_tipviv = 'PROPIA'
                   OR (scr_sertit IS NULL AND scr_relsti IS NULL))");

        DB::statement("ALTER TABLE solicitud_credito ADD CONSTRAINT ck_scr_ingres
            CHECK (scr_ingres >= 0)");

        DB::statement("ALTER TABLE solicitud_credito ADD CONSTRAINT ck_scr_plazo
            CHECK (scr_plazo > 0)");

        DB::statement("ALTER TABLE solicitud_credito ADD CONSTRAINT ck_scr_fechna
            CHECK (scr_fechna < CURRENT_DATE)");

        DB::statement("ALTER TABLE solicitud_credito ADD CONSTRAINT ck_scr_montos
            CHECK (scr_toncon >= 0 AND scr_recfin >= 0 AND scr_total >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_credito');
    }
};