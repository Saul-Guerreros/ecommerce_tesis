<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de motivos de rechazo, separado por ámbito.
 *
 * Los motivos del cheque no son variantes de los del comprobante: el
 * comprobante se rechaza por lo que muestra la imagen, el cheque por lo
 * que dice el papel físico o por no haber llegado nunca.
 */
class MotivoRechazoPagoSeeder extends Seeder
{
    public function run(): void
    {
        $motivos = [
            // ---------- Comprobante (transferencia / billetera) ----------
            [
                'mrp_codigo'  => 'ILEGIBLE',
                'mrp_descri'  => 'El comprobante es ilegible',
                'mrp_ambito'  => 'COMPROBANTE',
                'mrp_requdet' => false,
            ],
            [
                'mrp_codigo'  => 'OTRA_OPERACION',
                'mrp_descri'  => 'El comprobante corresponde a otra operación',
                'mrp_ambito'  => 'COMPROBANTE',
                'mrp_requdet' => false,
            ],
            [
                'mrp_codigo'  => 'NO_ENCONTRADO',
                'mrp_descri'  => 'No se encontró el pago en la cuenta',
                'mrp_ambito'  => 'COMPROBANTE',
                'mrp_requdet' => false,
            ],

            // ---------- Cheque ----------
            [
                'mrp_codigo'  => 'CHQ_DATOS_NO_COINCIDEN',
                'mrp_descri'  => 'Los datos no coinciden con lo declarado',
                'mrp_ambito'  => 'CHEQUE',
                'mrp_requdet' => true,
            ],
            [
                'mrp_codigo'  => 'CHQ_TITULAR_INCORRECTO',
                'mrp_descri'  => 'No emitido a nombre del titular receptor',
                'mrp_ambito'  => 'CHEQUE',
                'mrp_requdet' => false,
            ],
            [
                'mrp_codigo'  => 'CHQ_FUERA_DE_PLAZO',
                'mrp_descri'  => 'No entregado dentro del plazo',
                'mrp_ambito'  => 'CHEQUE',
                'mrp_requdet' => false,
            ],

            // ---------- Comunes ----------
            [
                'mrp_codigo'  => 'MONTO_NO_COINCIDE',
                'mrp_descri'  => 'El monto no coincide con el declarado',
                'mrp_ambito'  => 'AMBOS',
                'mrp_requdet' => true,
            ],
            [
                'mrp_codigo'  => 'OTRO',
                'mrp_descri'  => 'Otro',
                'mrp_ambito'  => 'AMBOS',
                'mrp_requdet' => true,
            ],
        ];

        foreach ($motivos as $motivo) {
            DB::table('motivo_rechazo_pago')->updateOrInsert(
                ['mrp_codigo' => $motivo['mrp_codigo']],
                [
                    'mrp_descri'  => $motivo['mrp_descri'],
                    'mrp_ambito'  => $motivo['mrp_ambito'],
                    'mrp_requdet' => $motivo['mrp_requdet'],
                    'mrp_estado'  => 1,
                    'mrp_creado'  => now(),
                ]
            );
        }
    }
}