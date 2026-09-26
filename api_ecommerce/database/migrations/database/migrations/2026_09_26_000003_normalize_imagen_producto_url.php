<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normaliza imagen_producto.img_url al formato que produce el controlador
 * de carga: ruta relativa al disco 'public', sin prefijo web.
 *
 * Motivo: algunas filas antiguas guardaban "/storage/productos/abc.jpg"
 * en vez de "productos/abc.jpg". Ese prefijo rompe dos cosas:
 *
 *   1. asset('storage/'.$img_url) genera ".../storage/storage/..." (404).
 *   2. Storage::disk('public')->delete($img_url) no encuentra el archivo,
 *      por lo que al borrar una imagen quedaba huérfana en el disco.
 *
 * Formato de referencia: PublicacionWebController usa
 * $archivo->store('productos', 'public'), que devuelve "productos/abc.jpg".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('imagen_producto')
            ->whereRaw("img_url ~ '^/?storage/'")
            ->update([
                'img_url' => DB::raw("REGEXP_REPLACE(img_url, '^/?storage/', '')"),
            ]);
    }

    /**
     * Sin reversa: el formato con prefijo era el incorrecto y restaurarlo
     * volvería a romper el borrado de archivos.
     */
    public function down(): void
    {
        //
    }
};