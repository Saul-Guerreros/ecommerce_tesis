<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Motivo de NC para devolver un anticipo facturado (guía 0.54.1, cap. 30):
 *   - mnc_dnit 2 = devolución.
 *   - mnc_afesto 0: no hay mercadería que reingresar, y el importe se
 *     acredita por PRECIO (permite NC parcial por el sobrante del anticipo).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('motivo_nota_credito')->updateOrInsert(
            ['mnc_codigo' => 'DANT'],
            [
                'mnc_descri' => 'Devolución de anticipo',
                'mnc_afesto' => 0,
                'mnc_canfij' => 0,
                'mnc_obsreq' => 0,
                'mnc_destex' => 0,
                'mnc_dnit'   => 2,
                'mnc_estado' => 1,
            ]
        );
    }

    public function down(): void
    {
        // Solo si nunca se usó: una NC emitida conserva su motivo.
        DB::table('motivo_nota_credito')
            ->where('mnc_codigo', 'DANT')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('nota_credito_venta')
                ->whereColumn('ncv_mncid', 'motivo_nota_credito.mnc_id'))
            ->delete();
    }
};