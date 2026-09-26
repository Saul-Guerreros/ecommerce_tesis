<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige un desfase entre la migración original (img_prdid -> producto)
 * y lo que el Model/Controller ya esperan (img_pubid -> publicacion_web).
 * Mismo caso que ya se dio con especificacion_producto (esp_proid -> esp_pubid):
 * la tabla imagen_producto se creó el 2026_08_10, antes de que existiera
 * publicacion_web (2026_08_30), así que en ese momento solo podía apuntar
 * a producto.
 * Idempotente: revisa el estado actual antes de tocar nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 0) Elimina primero el FK viejo hacia producto, si todavía existe
        //    (no se puede renombrar la columna con esa FK activa apuntando
        //    a una tabla distinta de forma segura sin antes soltarla).
        $fkViejo = DB::selectOne("
            SELECT conname FROM pg_constraint
            WHERE conrelid = 'imagen_producto'::regclass
            AND pg_get_constraintdef(oid) LIKE '%producto%'
            AND pg_get_constraintdef(oid) NOT LIKE '%publicacion_web%'
            AND contype = 'f'
        ");

        if ($fkViejo) {
            DB::statement('ALTER TABLE imagen_producto DROP CONSTRAINT "'.$fkViejo->conname.'"');
        }

        // 1) Rename, solo si todavía no se hizo
        if (Schema::hasColumn('imagen_producto', 'img_prdid')) {
            Schema::table('imagen_producto', function (Blueprint $table) {
                $table->renameColumn('img_prdid', 'img_pubid');
            });
        }

        // 2) FK hacia publicacion_web, solo si todavía no existe
        $fkExiste = DB::selectOne("
            SELECT conname FROM pg_constraint
            WHERE conrelid = 'imagen_producto'::regclass
            AND pg_get_constraintdef(oid) LIKE '%img_pubid%'
            AND pg_get_constraintdef(oid) LIKE '%publicacion_web%'
            AND contype = 'f'
        ");

        if (! $fkExiste) {
            Schema::table('imagen_producto', function (Blueprint $table) {
                $table->foreign('img_pubid', 'fk_imagen_producto_publicacion_web1')
                    ->references('pub_id')->on('publicacion_web');
            });
        }
    }

    public function down(): void
    {
        $constraint = DB::selectOne("
            SELECT conname FROM pg_constraint
            WHERE conrelid = 'imagen_producto'::regclass
            AND pg_get_constraintdef(oid) LIKE '%img_pubid%'
            AND pg_get_constraintdef(oid) LIKE '%publicacion_web%'
            AND contype = 'f'
        ");

        if ($constraint) {
            DB::statement('ALTER TABLE imagen_producto DROP CONSTRAINT "'.$constraint->conname.'"');
        }

        if (Schema::hasColumn('imagen_producto', 'img_pubid')) {
            Schema::table('imagen_producto', function (Blueprint $table) {
                $table->renameColumn('img_pubid', 'img_prdid');
            });
        }
    }
};
