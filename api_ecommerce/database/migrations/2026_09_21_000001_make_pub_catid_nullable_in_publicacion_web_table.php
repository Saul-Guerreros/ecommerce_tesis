<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * pub_catid pasa de NOT NULL a nullable.
 *
 * Motivo: al crear variantes en lote (ver ProductoController::crearVariantes),
 * cada variante nace con su Publicación Web en borrador de una, con la
 * especificación ya cargada -- pero la categoría se elige recién cuando el
 * admin entra a Publicación Web a terminar de completarla (ahí sí sigue
 * siendo obligatoria: StorePublicacionWebRequest no cambió). No tiene
 * sentido pedirla en el formulario de alta de variantes, así que la
 * columna tiene que poder nacer en null.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE publicacion_web ALTER COLUMN pub_catid DROP NOT NULL');
    }

    public function down(): void
    {
        // Si hay filas en null al momento de revertir, hay que resolverlas
        // antes (asignarles una categoría) o esta migración de vuelta va a
        // fallar -- a propósito, para no perder de vista datos incompletos.
        DB::statement('ALTER TABLE publicacion_web ALTER COLUMN pub_catid SET NOT NULL');
    }
};
