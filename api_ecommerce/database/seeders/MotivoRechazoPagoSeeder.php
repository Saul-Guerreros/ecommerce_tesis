<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MotivoRechazoPagoSeeder extends Seeder
{
    public function run(): void
    {
        $motivos = [
            [
                'mrp_codigo'  => 'ILEGIBLE',
                'mrp_descri'  => 'El comprobante es ilegible',
                'mrp_requdet' => false,
            ],
            [
                'mrp_codigo'  => 'MONTO_NO_COINCIDE',
                'mrp_descri'  => 'El monto no coincide con el declarado',
                'mrp_requdet' => true,
            ],
            [
                'mrp_codigo'  => 'OTRA_OPERACION',
                'mrp_descri'  => 'El comprobante corresponde a otra operación',
                'mrp_requdet' => false,
            ],
            [
                'mrp_codigo'  => 'NO_ENCONTRADO',
                'mrp_descri'  => 'No se encontró el pago en la cuenta',
                'mrp_requdet' => false,
            ],
            [
                'mrp_codigo'  => 'OTRO',
                'mrp_descri'  => 'Otro',
                'mrp_requdet' => true,
            ],
        ];

        foreach ($motivos as $motivo) {
            DB::table('motivo_rechazo_pago')->updateOrInsert(
                ['mrp_codigo' => $motivo['mrp_codigo']],
                [
                    'mrp_descri'  => $motivo['mrp_descri'],
                    'mrp_requdet' => $motivo['mrp_requdet'],
                    'mrp_estado'  => 1,
                    'mrp_creado'  => now(),
                ]
            );
        }
    }
}