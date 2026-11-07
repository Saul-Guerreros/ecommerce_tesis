<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El anticipo pasa a tener factura propia (tipo_transaccion 9) y se aplica
 * en la factura final por su CDC (guía 0.54.1, cap. 29).
 *
 *   - Se elimina ck_spa_anticipo: el anticipo ahora también se paga por la
 *     web (transferencia con comprobante), no solo en el mostrador.
 *   - "Un anticipo por solicitud" NO se fuerza con un índice: depende del
 *     estado del pago (un anticipo rechazado libera el lugar) y estado_pago
 *     usa ids, no códigos fijos. Lo controla el service con un lock.
 *
 * Datos del ciclo anterior (anticipo SIN factura propia):
 *   - En solicitudes ya facturadas, el anticipo quedó declarado como pago
 *     de esa factura: se reclasifica como SALDO.
 *   - En solicitudes sin factura hay dinero recibido sin documento y no
 *     tiene una factura de anticipo que aplicar: la migración se detiene y
 *     los lista para resolverlos a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sinFactura = DB::table('servicio_pago as spa')
            ->join('solicitud_servicio as sse', 'sse.sse_id', '=', 'spa.spa_sseid')
            ->join('estado_pago as epa', 'epa.epa_id', '=', 'spa.spa_epaid')
            ->where('spa.spa_tipo', 'ANTICIPO')
            ->where('epa.epa_codigo', '<>', 'RECHAZADO')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('venta_cab as ven')
                  ->whereColumn('ven.ven_sseid', 'spa.spa_sseid')
                  ->where('ven.ven_estado', '<>', 9);
            })
            ->distinct()
            ->pluck('sse.sse_numero');

        if ($sinFactura->isNotEmpty()) {
            throw new RuntimeException(
                'Anticipos del ciclo anterior sin factura: ' . $sinFactura->implode(', ')
                . '. Resolvelos antes de migrar (no tienen factura de anticipo que aplicar).'
            );
        }

        DB::statement("
            UPDATE servicio_pago SET spa_tipo = 'SALDO'
             WHERE spa_tipo = 'ANTICIPO'
               AND EXISTS (SELECT 1 FROM venta_cab v
                            WHERE v.ven_sseid = servicio_pago.spa_sseid AND v.ven_estado <> 9)
        ");

        DB::statement('ALTER TABLE servicio_pago DROP CONSTRAINT IF EXISTS ck_spa_anticipo');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE servicio_pago ADD CONSTRAINT ck_spa_anticipo
            CHECK (spa_tipo <> 'ANTICIPO' OR spa_canal = 'MOSTRADOR')");
    }
};