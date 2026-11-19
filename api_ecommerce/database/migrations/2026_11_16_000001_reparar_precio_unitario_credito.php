<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos y facturas a credito creados antes de que
 * CheckoutCreditoService::crearDetalle() guardara el precio FINANCIADO:
 * quedaron con el unitario de contado y el total con recargo, es decir
 * precio x cantidad != total. El builder de facturacion electronica
 * rechaza esas lineas ("el detalle suma Gs. X pero el total es Gs. Y")
 * y la factura no se puede declarar nunca.
 *
 * Se reescribe el unitario a partir del total, que es lo que el cliente
 * realmente debe y lo que respaldan las cuotas de cta_cobrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Pedidos: alcanza a los que todavia no se facturaron.
        DB::statement("
            UPDATE detalle_pedido
               SET dpd_precio = ROUND(dpd_total / dpd_cantid, 2)
             WHERE dpd_cantid > 0
               AND ABS(dpd_precio * dpd_cantid - dpd_total) > 0.01
        ");

        // 2) Facturas ya emitidas pero nunca declaradas (sin CDC): su XML
        //    no existe, asi que el detalle se puede corregir sin romper
        //    nada enviado a la DNIT.
        DB::statement("
            UPDATE venta_detalle AS vd
               SET vnd_precio = ROUND(vd.vnd_valorventa / vd.vnd_cantidad, 4)
              FROM venta_cab AS vc
         LEFT JOIN venta_fe AS vf ON vf.vfe_venid = vc.ven_id
             WHERE vd.vnd_venid = vc.ven_id
               AND vd.vnd_cantidad > 0
               AND ABS(vd.vnd_precio * vd.vnd_cantidad - vd.vnd_valorventa) > 0.01
               AND vf.vfe_cdc IS NULL
        ");
    }

    public function down(): void
    {
        // Irreversible a proposito: el valor anterior dejaba la linea
        // incoherente y la factura imposible de declarar.
    }
};