<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Análisis de comportamiento: cada búsqueda con texto de la tienda o del chatbot.
 * Solo la página 1 y deduplicada por visitante (ver ActividadClienteService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('busqueda', function (Blueprint $table) {
            $table->bigIncrements('bus_id');
            $table->string('bus_termino', 150);              // tal cual lo escribió el cliente
            $table->string('bus_termnorm', 150);             // minúsculas, sin tildes ni espacios dobles
            $table->integer('bus_total')->default(0);        // resultados devueltos
            $table->string('bus_modo', 15);                  // todos|parcial|aproximado|sin_resultados|ia
            $table->string('bus_origen', 10)->default('TIENDA');
            $table->unsignedInteger('bus_usuid')->nullable(); // cliente logueado
            $table->uuid('bus_visita')->nullable();           // visitante anónimo (localStorage)
            $table->timestamp('bus_creado')->useCurrent();

            $table->foreign('bus_usuid', 'fk_busqueda_usuario')
                ->references('usu_id')->on('usuario')->nullOnDelete();

            $table->index('bus_creado', 'ix_bus_creado');
            $table->index(['bus_termnorm', 'bus_creado'], 'ix_bus_termino');
            $table->index(['bus_usuid', 'bus_creado'], 'ix_bus_usuario');
        });

        DB::statement("ALTER TABLE busqueda ADD CONSTRAINT ck_bus_origen CHECK (bus_origen IN ('TIENDA', 'CHATBOT'))");
        DB::statement("ALTER TABLE busqueda ADD CONSTRAINT ck_bus_total CHECK (bus_total >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('busqueda');
    }
};