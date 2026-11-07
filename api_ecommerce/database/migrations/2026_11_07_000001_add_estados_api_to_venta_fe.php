<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Estados de Facturacion_API que dejan un documento SIN VALIDEZ aunque
 * tenga archivos (guía 0.54.1, 9.x y 10.3):
 *   5 RETIRADO     envío pendiente retirado en el portal (reactivable).
 *   6 CANCELADO    evento de cancelación aprobado (definitivo).
 *   7 INUTILIZADO  número inutilizado (definitivo, no se reactiva).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE venta_fe DROP CONSTRAINT IF EXISTS ck_vfe_estado');
        DB::statement('ALTER TABLE venta_fe ADD CONSTRAINT ck_vfe_estado
            CHECK (vfe_estado IN (1, 2, 3, 4, 5, 6, 7))');
    }

    public function down(): void
    {
        // Sin equivalente previo: quedan como RECHAZADO (sin validez).
        DB::statement('UPDATE venta_fe SET vfe_estado = 3 WHERE vfe_estado IN (5, 6, 7)');
        DB::statement('ALTER TABLE venta_fe DROP CONSTRAINT IF EXISTS ck_vfe_estado');
        DB::statement('ALTER TABLE venta_fe ADD CONSTRAINT ck_vfe_estado
            CHECK (vfe_estado IN (1, 2, 3, 4))');
    }
};