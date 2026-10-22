<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Indices del Modulo de Metricas y Reportes.
 *
 * - Prefijo ix_rep_ para identificarlos.
 * - Indices PARCIALES (WHERE) donde el reporte siempre filtra lo mismo
 *   (facturas vigentes, compras confirmadas, cuotas pendientes): son mas
 *   chicos y PostgreSQL los usa directamente.
 * - IF NOT EXISTS: la migracion es segura aunque alguno ya exista.
 */
return new class extends Migration
{
    private const INDICES = [
        // Ventas: filtro sucursal + rango de fechas sobre facturas vigentes
        'ix_rep_ven_suc_fecha'  => 'venta_cab (ven_sucid, ven_fchaem) WHERE ven_estado <> 9',
        // Compras confirmadas por sucursal/fecha y por proveedor
        'ix_rep_com_suc_fecha'  => 'compra_cab (com_sucid, com_fchaem) WHERE com_estfac = 1',
        'ix_rep_com_pro_fecha'  => 'compra_cab (com_proid, com_fchaem) WHERE com_estfac = 1',
        // Pedidos del mes / ultimos pedidos
        'ix_rep_ped_creado'     => 'pedido (ped_creado)',
        'ix_rep_ped_suc_creado' => 'pedido (ped_sucid, ped_creado)',
        // FKs sin indice usadas en JOIN
        'ix_rep_dpd_pedid'      => 'detalle_pedido (dpd_pedid)',
        'ix_rep_pen_pedid'      => 'pedido_entrega (pen_pedid)',
        'ix_rep_sto_depid'      => 'stock (sto_depid)',
        'ix_rep_pub_catid'      => 'publicacion_web (pub_catid)',
        'ix_rep_prd_marid'      => 'producto (prd_marid)',
        'ix_rep_prd_padre'      => 'producto (prd_padre_id)',
        'ix_rep_sse_sucid'      => 'solicitud_servicio (sse_sucid)',
        // Cuentas por pagar pendientes ordenadas por vencimiento
        'ix_rep_cuo_pendiente'  => 'cuota_compra (cuo_venci) WHERE cuo_estado = 0',
        // Productos sin movimiento: ultima salida por producto
        'ix_rep_mst_salida'     => "movimiento_stock (mst_stprid, mst_fecha) WHERE mst_tipo = 'S'",
        // Clientes registrados por mes
        'ix_rep_cli_creado'     => 'cliente (cli_creado)',
        // Servicio tecnico: ordenes abiertas por tecnico
        'ix_rep_ose_abiertas'   => 'orden_servicio (ose_usuid) WHERE ose_fecfin IS NULL',
        // Chatbot
        'ix_rep_cnv_creado'     => 'chat_conversacion (cnv_creado)',
        'ix_rep_cms_rol_fecha'  => 'chat_mensaje (cms_rol, cms_creado)',
        'ix_rep_cms_herram'     => 'chat_mensaje (cms_herramienta) WHERE cms_herramienta IS NOT NULL',
    ];

    public function up(): void
    {
        foreach (self::INDICES as $nombre => $definicion) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$nombre} ON {$definicion}");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDICES) as $nombre) {
            DB::statement("DROP INDEX IF EXISTS {$nombre}");
        }
    }
};