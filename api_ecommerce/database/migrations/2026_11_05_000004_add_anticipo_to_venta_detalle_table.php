<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anticipo aplicado POR UNIDAD, con IVA incluido (items[].anticipo, guía 29).
 *
 *   vnd_precio      precio cobrado por unidad (ya con descuento comercial)
 *   vnd_anticipo    parte del precio unitario cubierta por el anticipo
 *   vnd_valorventa  cantidad × (precio − anticipo) − descuento  = NETO
 *   vnd_iva         IVA contenido en el NETO (NT 013)
 *
 * Cuatro decimales, igual que vnd_precio: con cantidad > 1 el importe por
 * unidad puede no ser entero (guía 29: no redondear cada unidad).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_detalle', function (Blueprint $table) {
            $table->decimal('vnd_anticipo', 14, 4)->default(0)->after('vnd_precio');
        });

        DB::statement('ALTER TABLE venta_detalle ADD CONSTRAINT ck_vnd_anticipo
            CHECK (vnd_anticipo >= 0 AND vnd_anticipo <= vnd_precio)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE venta_detalle DROP CONSTRAINT IF EXISTS ck_vnd_anticipo');

        Schema::table('venta_detalle', function (Blueprint $table) {
            $table->dropColumn('vnd_anticipo');
        });
    }
};