<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Limpieza de datos para la HERENCIA DINÁMICA de la ficha técnica.
 *
 * Hasta ahora, al publicar una variante se COPIABAN las especificaciones
 * del producto base (duplicarEspecificaciones). Con la herencia dinámica
 * esas copias sobran: se leen del base en tiempo real. Además generaban
 * un error visible: la variante quedaba con dos colores (el copiado del
 * base y el propio).
 *
 * Regla: se borra toda fila de una variante que sea IDÉNTICA a una fila
 * del producto base para el mismo atributo (misma propiedad, mismo texto,
 * mismo número). Lo que difiere (el atributo propio) se conserva.
 * IS NOT DISTINCT FROM compara también los NULL como iguales (PostgreSQL).
 *
 * Irreversible a propósito: las filas borradas eran copias exactas y
 * siguen visibles por herencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DELETE FROM especificacion_producto v
            USING publicacion_web pv,
                  producto p,
                  publicacion_web pb,
                  especificacion_producto b
            WHERE v.esp_pubid = pv.pub_id
              AND pv.pub_prdid = p.prd_id
              AND p.prd_padre_id IS NOT NULL
              AND pb.pub_prdid = p.prd_padre_id
              AND b.esp_pubid = pb.pub_id
              AND b.esp_atbid = v.esp_atbid
              AND b.esp_prpid  IS NOT DISTINCT FROM v.esp_prpid
              AND b.esp_valibr IS NOT DISTINCT FROM v.esp_valibr
              AND b.esp_valnum IS NOT DISTINCT FROM v.esp_valnum
        SQL);
    }

    public function down(): void
    {
        // Sin reversión: ver nota de la clase.
    }
};
