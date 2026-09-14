<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Quitar la FK y la columna de categoria: pertenece al futuro
        //    modulo de Catalogo/publicacion_web, no a Compras/Inventario.
        Schema::table('producto', function (Blueprint $table) {
            $table->dropForeign('fk_producto_categoria1');
            $table->dropColumn('prd_catid');
        });

        // 2) prd_descri pasa de TEXT a VARCHAR(150): ahora es el "nombre"
        //    del producto (absorbe el rol de prd_titulo), no la descripcion
        //    larga de venta. Se trunca lo que exceda 150 caracteres.
        DB::statement("ALTER TABLE producto
            ALTER COLUMN prd_descri TYPE VARCHAR(150)
            USING LEFT(COALESCE(prd_descri, prd_titulo, 'Sin nombre'), 150)");
        DB::statement("ALTER TABLE producto ALTER COLUMN prd_descri SET NOT NULL");

        Schema::table('producto', function (Blueprint $table) {
            // 3) prd_titulo y prd_stock se eliminan (stock pasa a ser
            //    derivado del kardex, ver movimiento_stock/stock).
            $table->dropColumn(['prd_titulo', 'prd_stock']);

            // 4) prd_precio -> prd_prevta (renombra y ajusta precision al DER)
            $table->renameColumn('prd_precio', 'prd_prevta');
        });

        // prd_prevta queda NULLABLE, SIN default: el producto nace sin
        // precio de venta. Recien se completa cuando existe una compra
        // confirmada (CompraService) o una edicion desde Gestion de
        // Precios (PrecioService). Nunca se fuerza un valor "inventado"
        // como 0 al crear el producto.
        DB::statement("ALTER TABLE producto ALTER COLUMN prd_prevta TYPE DECIMAL(14,2)");

        // 5) Nuevas columnas del DER
        Schema::table('producto', function (Blueprint $table) {
            $table->unsignedInteger('prd_tivid')->nullable()->after('prd_marid');
            $table->unsignedInteger('prd_umdid')->nullable()->after('prd_tivid');
            $table->smallInteger('prd_afestk')->default(1)->after('prd_estado');

            // Campo auxiliar para sugerir precio de venta en base al costo
            // promedio ponderado (sto_cosprm). NUNCA pisa prd_prevta solo;
            // el CompraService lo usa para recalcular prd_prevta al
            // confirmar una compra (ver flujo de CPP).
            $table->decimal('prd_margen', 14, 4)->nullable()->after('prd_prevta');

            $table->foreign('prd_tivid', 'fk_prd_tiv')
                  ->references('tiv_id')->on('tipo_iva')
                  ->onUpdate('cascade')->onDelete('restrict');

            $table->foreign('prd_umdid', 'fk_prd_umd')
                  ->references('umd_id')->on('unidad_medida')
                  ->onUpdate('cascade')->onDelete('restrict');
        });

        DB::statement("ALTER TABLE producto
            ADD CONSTRAINT ck_prd_margen CHECK (prd_margen IS NULL OR prd_margen >= 0)");

        // prd_stkmin pasa de integer a decimal (el DER lo pide DECIMAL(14,4)).
        DB::statement("ALTER TABLE producto ALTER COLUMN prd_stkmin TYPE DECIMAL(14,4)");

        DB::statement("ALTER TABLE producto
            ADD CONSTRAINT ck_prd_afestk CHECK (prd_afestk IN (0, 1))");
    }

    public function down(): void
    {
        throw new \RuntimeException('Esta migración no es reversible. Restaurar desde backup si es necesario.');
    }
};