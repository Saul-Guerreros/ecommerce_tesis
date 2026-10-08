<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unicidad de dir_alias POR CLIENTE.
 *
 * La validacion del FormRequest resuelve el 99% de los casos, pero deja
 * una ventana de carrera: dos requests simultaneos (doble clic en
 * "Guardar", dos pestañas del checkout abiertas) pueden pasar ambos el
 * SELECT antes de que cualquiera inserte. El indice unico es la unica
 * garantia a nivel de datos.
 *
 * El indice es FUNCIONAL y PARCIAL:
 *
 *  - FUNCIONAL porque la unicidad no es sobre el texto crudo sino sobre
 *    su forma normalizada: "Mi Casa", "mi casa" y " Mi  Casa " deben
 *    colisionar entre si.
 *
 *  - PARCIAL (WHERE dir_estado = 1) porque el borrado de direcciones es
 *    logico: la fila sigue en la tabla con dir_estado = 0 para que los
 *    pedidos historicos conserven su referencia. Sin el WHERE, un
 *    "Mi Casa" borrado bloquearia para siempre la creacion de otro.
 *
 * SOBRE normalizar_alias():
 *
 * No usa la extension unaccent, por dos motivos:
 *
 *  1. PostgreSQL evalua las expresiones de un indice con un search_path
 *     restringido a pg_catalog y pg_temp, para impedir que alguien
 *     secuestre el indice definiendo una funcion homonima en otro
 *     esquema. unaccent() vive en public, asi que dentro del indice
 *     deja de resolverse: ese es el error 42883 "no existe la funcion
 *     unaccent(text)". Calificar el esquema (public.unaccent) lo
 *     evitaria, pero queda el motivo 2.
 *
 *  2. unaccent(text) depende del diccionario instalado en la base, por
 *     eso PostgreSQL la declara STABLE y no IMMUTABLE. Envolverla en
 *     una funcion IMMUTABLE para poder indexarla es mentirle al
 *     planificador: si el diccionario cambia, el indice queda
 *     silenciosamente corrupto.
 *
 * translate() es de pg_catalog, IMMUTABLE de verdad y no necesita
 * CREATE EXTENSION (que exige superusuario, algo que el usuario de la
 * base en produccion puede no tener). Cubre el juego de acentos del
 * español, que es lo unico que puede aparecer en el alias.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE FUNCTION public.normalizar_alias(texto TEXT)
            RETURNS TEXT AS \$\$
                SELECT pg_catalog.translate(
                           pg_catalog.lower(
                               pg_catalog.regexp_replace(pg_catalog.btrim(texto), '\\s+', ' ', 'g')
                           ),
                           'áàäâãéèëêíìïîóòöôõúùüûñç',
                           'aaaaaeeeeiiiiooooouuuunc'
                       )
            \$\$ LANGUAGE SQL IMMUTABLE
        ");

        // Red de seguridad para entornos con datos ya cargados: si hay
        // duplicados activos, el CREATE INDEX falla. Se renombran con un
        // sufijo incremental. Sin duplicados es un no-op.
        DB::statement("
            WITH duplicados AS (
                SELECT dir_id,
                       ROW_NUMBER() OVER (
                           PARTITION BY dir_perid, public.normalizar_alias(dir_alias)
                           ORDER BY dir_id
                       ) AS fila
                  FROM direccion
                 WHERE dir_estado = 1
            )
            UPDATE direccion d
               SET dir_alias = LEFT(d.dir_alias, 45) || ' (' || x.fila || ')'
              FROM duplicados x
             WHERE d.dir_id = x.dir_id
               AND x.fila > 1
        ");

        DB::statement("
            CREATE UNIQUE INDEX ux_direccion_perid_alias
                ON direccion (dir_perid, public.normalizar_alias(dir_alias))
             WHERE dir_estado = 1
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ux_direccion_perid_alias');
        DB::statement('DROP FUNCTION IF EXISTS public.normalizar_alias(TEXT)');
    }
};