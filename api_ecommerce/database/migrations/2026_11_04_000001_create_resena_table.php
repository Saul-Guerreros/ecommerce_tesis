<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * resena: opinión de un cliente sobre un producto que COMPRÓ y RECIBIÓ.
 *
 * - res_prdid: el producto exacto que compró (ej. la variante "Gris").
 * - res_grpid: el modelo base (prd_padre_id ?? prd_id). Las reseñas se
 *   comparten entre todas las variantes de color, así que el promedio y
 *   la regla "una reseña por cliente" se calculan por grupo.
 * - res_pedid: pedido ENTREGADO que respalda la "compra verificada".
 * - res_estado: 1 visible / 0 oculta por el administrador (post-moderación).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resena', function (Blueprint $table) {
            $table->increments('res_id');

            $table->unsignedInteger('res_prdid');
            $table->unsignedInteger('res_grpid');
            $table->unsignedBigInteger('res_cliid');
            $table->unsignedBigInteger('res_pedid');

            $table->smallInteger('res_califi');
            $table->string('res_titulo', 80)->nullable();
            $table->string('res_coment', 1000);
            $table->boolean('res_editad')->default(false);

            // Moderación
            $table->smallInteger('res_estado')->default(1);
            $table->string('res_motocu', 255)->nullable();

            // Respuesta pública de la tienda
            $table->string('res_respue', 1000)->nullable();
            $table->timestamp('res_fecres')->nullable();
            $table->unsignedInteger('res_usures')->nullable();

            $table->timestamp('res_creado')->useCurrent();
            $table->timestamp('res_actual')->nullable();

            // Un cliente opina una sola vez por modelo (incluye sus variantes).
            $table->unique(['res_cliid', 'res_grpid'], 'uq_resena_cliente_grupo');
            $table->index(['res_grpid', 'res_estado', 'res_creado'], 'ix_resena_grupo');

            $table->foreign('res_prdid', 'fk_resena_producto')
                ->references('prd_id')->on('producto')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('res_grpid', 'fk_resena_grupo')
                ->references('prd_id')->on('producto')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('res_cliid', 'fk_resena_cliente')
                ->references('cli_id')->on('cliente')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('res_pedid', 'fk_resena_pedido')
                ->references('ped_id')->on('pedido')
                ->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('res_usures', 'fk_resena_usuario_respuesta')
                ->references('usu_id')->on('usuario')
                ->onUpdate('cascade')->onDelete('set null');
        });

        DB::statement('ALTER TABLE resena ADD CONSTRAINT ck_res_califi CHECK (res_califi BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE resena ADD CONSTRAINT ck_res_estado CHECK (res_estado IN (0, 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('resena');
    }
};