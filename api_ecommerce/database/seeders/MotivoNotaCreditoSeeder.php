<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo compartido por las NC de compra y de venta.
 *
 * mnc_afesto : mueve mercadería física (la DIRECCIÓN la pone el documento:
 *              en compra es salida, en venta es entrada).
 * mnc_canfij : cantidad fija = saldo de unidades vigentes.
 * mnc_obsreq : observación obligatoria.
 * mnc_destex : exige elegir el destino de la mercadería (sin sugerido).
 * mnc_dnit   : código de motivo del Manual Técnico (NC electrónica futura).
 */
class MotivoNotaCreditoSeeder extends Seeder
{
    public function run(): void
    {
        $motivos = [
            ['mnc_codigo' => 'DESC', 'mnc_descri' => 'Descuento o bonificación posterior', 'mnc_afesto' => 0, 'mnc_canfij' => 0, 'mnc_obsreq' => 0, 'mnc_destex' => 0, 'mnc_dnit' => 3],
            ['mnc_codigo' => 'EPRE', 'mnc_descri' => 'Error en precio facturado',          'mnc_afesto' => 0, 'mnc_canfij' => 1, 'mnc_obsreq' => 1, 'mnc_destex' => 0, 'mnc_dnit' => 8],
            ['mnc_codigo' => 'ECAN', 'mnc_descri' => 'Diferencia de cantidad facturada',   'mnc_afesto' => 1, 'mnc_canfij' => 0, 'mnc_obsreq' => 0, 'mnc_destex' => 0, 'mnc_dnit' => 1],
            ['mnc_codigo' => 'DDEF', 'mnc_descri' => 'Devolución por producto defectuoso', 'mnc_afesto' => 1, 'mnc_canfij' => 0, 'mnc_obsreq' => 1, 'mnc_destex' => 1, 'mnc_dnit' => 2],
            ['mnc_codigo' => 'DOTR', 'mnc_descri' => 'Devolución por otros motivos',       'mnc_afesto' => 1, 'mnc_canfij' => 0, 'mnc_obsreq' => 1, 'mnc_destex' => 0, 'mnc_dnit' => 2],
            ['mnc_codigo' => 'DANT', 'mnc_descri' => 'Devolución de anticipo',             'mnc_afesto' => 0, 'mnc_canfij' => 0, 'mnc_obsreq' => 0, 'mnc_destex' => 0, 'mnc_dnit' => 2],
        ];

        // Solo se escriben las columnas que existen: este seeder lo invocan
        // migraciones anteriores a las que agregaron mnc_dnit y las banderas,
        // y en un migrate:fresh fallarían con "columna inexistente".
        $existentes = array_flip(Schema::getColumnListing('motivo_nota_credito'));

        foreach ($motivos as $motivo) {
            $fila = array_intersect_key($motivo + ['mnc_estado' => 1], $existentes);

            DB::table('motivo_nota_credito')->updateOrInsert(
                ['mnc_codigo' => $motivo['mnc_codigo']],
                $fila
            );
        }
    }
}