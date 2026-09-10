<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige un desfase entre la migración original (esp_proid -> producto)
 * y lo que el Model/Controller ya esperan (esp_pubid -> publicacion_web).
 * Idempotente: revisa el estado actual antes de tocar nada, porque un
 * intento anterior pudo haber quedado a mitad de camino.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Rename, solo si todavía no se hizo
        if (Schema::hasColumn('especificacion_producto', 'esp_proid')) {
            Schema::table('especificacion_producto', function (Blueprint $table) {
                $table->renameColumn('esp_proid', 'esp_pubid');
            });
        }

        // 2) FK hacia publicacion_web, solo si todavía no existe
        $fkExiste = DB::selectOne("
            SELECT conname FROM pg_constraint
            WHERE conrelid = 'especificacion_producto'::regclass
            AND pg_get_constraintdef(oid) LIKE '%esp_pubid%'
            AND contype = 'f'
        ");

        if (! $fkExiste) {
            Schema::table('especificacion_producto', function (Blueprint $table) {
                $table->foreign('esp_pubid')->references('pub_id')->on('publicacion_web');
            });
        }
    }

    public function down(): void
    {
        $constraint = DB::selectOne("
            SELECT conname FROM pg_constraint
            WHERE conrelid = 'especificacion_producto'::regclass
            AND pg_get_constraintdef(oid) LIKE '%esp_pubid%'
            AND contype = 'f'
        ");

        if ($constraint) {
            DB::statement('ALTER TABLE especificacion_producto DROP CONSTRAINT "'.$constraint->conname.'"');
        }

        if (Schema::hasColumn('especificacion_producto', 'esp_pubid')) {
            Schema::table('especificacion_producto', function (Blueprint $table) {
                $table->renameColumn('esp_pubid', 'esp_proid');
            });
        }
    }
};