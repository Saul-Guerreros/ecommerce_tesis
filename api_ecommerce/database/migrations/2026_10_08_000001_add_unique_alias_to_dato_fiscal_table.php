<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unicidad de dfi_alias POR CLIENTE. Mismo criterio que
 * ux_direccion_perid_alias (vease 2026_10_07_000001): la validacion del
 * FormRequest cubre el caso normal, este indice cierra la ventana de
 * carrera de dos requests simultaneos.
 *
 * Reutiliza public.normalizar_alias(), creada en la migracion de
 * direccion. No se redefine aca: es la misma normalizacion (trim +
 * colapsar espacios + minusculas + sin acentos) y debe comportarse
 * igual para cualquier alias del sistema, sea de una direccion o de
 * un RUC.
 *
 * A diferencia de direccion, ESTE indice no lleva WHERE: dato_fiscal no
 * tiene borrado logico. pedido_facturacion.pfa_dfiid referencia
 * dato_fiscal con ON DELETE SET NULL (no RESTRICT como pasaba con
 * direccion), asi que eliminar un RUC es un DELETE fisico real y no
 * hace falta reservar su alias para un historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Red de seguridad para entornos con datos ya cargados: si hay
        // duplicados, el CREATE INDEX falla. Se renombran con un sufijo
        // incremental. Sin duplicados es un no-op.
        DB::statement("
            WITH duplicados AS (
                SELECT dfi_id,
                       ROW_NUMBER() OVER (
                           PARTITION BY dfi_perid, public.normalizar_alias(dfi_alias)
                           ORDER BY dfi_id
                       ) AS fila
                  FROM dato_fiscal
            )
            UPDATE dato_fiscal d
               SET dfi_alias = LEFT(d.dfi_alias, 45) || ' (' || x.fila || ')'
              FROM duplicados x
             WHERE d.dfi_id = x.dfi_id
               AND x.fila > 1
        ");

        DB::statement("
            CREATE UNIQUE INDEX ux_dato_fiscal_perid_alias
                ON dato_fiscal (dfi_perid, public.normalizar_alias(dfi_alias))
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ux_dato_fiscal_perid_alias');
    }
};