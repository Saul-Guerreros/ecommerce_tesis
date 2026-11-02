<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alertas de reposición generadas por el cálculo de inventario inteligente.
 *
 *   ast_tipo    agotado | critico | reponer | sobrestock
 *   ast_estado  1 Pendiente · 2 Atendida · 3 Descartada
 *
 * Snapshot del cálculo (no se recalcula al consultar):
 *   ast_dispon  Disponible (sto_cantid - sto_reserv) al generar la alerta.
 *   ast_demdia  Demanda diaria prevista.
 *   ast_ss      Stock de seguridad.   ast_rop  Punto de reorden.
 *   ast_sugeri  Cantidad sugerida a comprar.
 *   ast_cobert  Días de cobertura (NULL si la demanda prevista es 0).
 *
 * Regla: como máximo UNA alerta pendiente por producto y depósito
 * (índice único parcial). Si la situación cambia, se actualiza esa misma
 * alerta en lugar de crear duplicados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerta_stock', function (Blueprint $table) {
            $table->bigIncrements('ast_id');
            $table->unsignedInteger('ast_prdid');
            $table->unsignedInteger('ast_depid');
            $table->string('ast_tipo', 12);
            $table->decimal('ast_dispon', 14, 4);
            $table->decimal('ast_demdia', 14, 4);
            $table->decimal('ast_ss', 14, 4);
            $table->decimal('ast_rop', 14, 4);
            $table->decimal('ast_sugeri', 14, 4)->default(0);
            $table->decimal('ast_cobert', 10, 2)->nullable();
            $table->smallInteger('ast_ltdias');
            $table->string('ast_modelo', 20)->nullable();
            $table->smallInteger('ast_estado')->default(1);
            $table->unsignedInteger('ast_usuid')->nullable();   // quien la atendió/descartó
            $table->string('ast_obs', 255)->nullable();
            $table->timestamp('ast_creado')->useCurrent();
            $table->timestamp('ast_actual')->useCurrent();       // último recálculo
            $table->timestamp('ast_cerrado')->nullable();        // atendida/descartada

            $table->foreign('ast_prdid', 'fk_ast_prd')
                  ->references('prd_id')->on('producto')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('ast_depid', 'fk_ast_dep')
                  ->references('dpo_id')->on('deposito')
                  ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('ast_usuid', 'fk_ast_usu')
                  ->references('usu_id')->on('usuario')
                  ->onUpdate('cascade')->onDelete('set null');

            $table->index(['ast_estado', 'ast_tipo'], 'ix_ast_estado_tipo');
            $table->index('ast_depid', 'ix_ast_depid');
        });

        DB::statement("ALTER TABLE alerta_stock
            ADD CONSTRAINT ck_ast_tipo   CHECK (ast_tipo IN ('agotado', 'critico', 'reponer', 'sobrestock')),
            ADD CONSTRAINT ck_ast_estado CHECK (ast_estado IN (1, 2, 3))");

        DB::statement('CREATE UNIQUE INDEX uq_ast_pendiente
            ON alerta_stock (ast_prdid, ast_depid) WHERE ast_estado = 1');
    }

    public function down(): void
    {
        Schema::dropIfExists('alerta_stock');
    }
};
