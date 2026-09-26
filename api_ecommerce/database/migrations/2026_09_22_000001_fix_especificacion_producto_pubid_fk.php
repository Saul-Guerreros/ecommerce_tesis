<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repara un bug de fix_especificacion_producto_pubid (2026_09_05): esa
 * migración renombró esp_proid -> esp_pubid pero NUNCA soltó la llave
 * foránea vieja (la que apuntaba a producto), y su chequeo de "¿ya existe
 * la FK correcta?" solo miraba si "esp_pubid" aparecía en la definición
 * -- que, tras el rename, también es cierto para la FK VIEJA. Resultado:
 * nunca se creó la FK hacia publicacion_web, y la columna esp_pubid sigue
 * validando contra producto.prd_id hasta hoy (por eso el 23503 al insertar
 * una especificación con un pub_id real).
 *
 * Mismo patrón ya usado correctamente en fix_imagen_producto_pubid: soltar
 * la FK vieja primero, después crear la que corresponde.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fkViejo = DB::selectOne("
            SELECT conname FROM pg_constraint
            WHERE conrelid = 'especificacion_producto'::regclass
            AND pg_get_constraintdef(oid) LIKE '%producto%'
            AND pg_get_constraintdef(oid) NOT LIKE '%publicacion_web%'
            AND contype = 'f'
        ");

        if ($fkViejo) {
            DB::statement('ALTER TABLE especificacion_producto DROP CONSTRAINT "'.$fkViejo->conname.'"');
        }

        $fkCorrecta = DB::selectOne("
            SELECT conname FROM pg_constraint
            WHERE conrelid = 'especificacion_producto'::regclass
            AND pg_get_constraintdef(oid) LIKE '%esp_pubid%'
            AND pg_get_constraintdef(oid) LIKE '%publicacion_web%'
            AND contype = 'f'
        ");

        if (! $fkCorrecta) {
            Schema::table('especificacion_producto', function (Blueprint $table) {
                $table->foreign('esp_pubid', 'fk_especificacion_producto_publicacion_web1')
                    ->references('pub_id')->on('publicacion_web')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        $constraint = DB::selectOne("
            SELECT conname FROM pg_constraint
            WHERE conrelid = 'especificacion_producto'::regclass
            AND pg_get_constraintdef(oid) LIKE '%esp_pubid%'
            AND pg_get_constraintdef(oid) LIKE '%publicacion_web%'
            AND contype = 'f'
        ");

        if ($constraint) {
            DB::statement('ALTER TABLE especificacion_producto DROP CONSTRAINT "'.$constraint->conname.'"');
        }

        // No se recrea la FK vieja hacia producto a propósito: para 2026-09-22
        // ya no tiene sentido (la columna representa esp_pubid conceptualmente
        // desde hace rato), y si alguna especificación ya usa un pub_id que
        // no coincide con ningún prd_id, ese down() fallaría igual.
    }
};
