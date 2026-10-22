<?php

namespace App\Services\Reporte;

use Illuminate\Support\Facades\DB;

/**
 * Reporte de Promociones (alcance acotado — decisión 3b del Paso 1).
 *
 * El checkout NO registra qué cupón o campaña se aplicó en cada pedido
 * (pedido / detalle_pedido no tienen esa referencia), así que el USO y las
 * ventas por campaña no son medibles todavía: quedan como trabajo futuro.
 * Este reporte informa la CONFIGURACIÓN vigente:
 *   - Cupones: tipo, valor, límite de uso, alcance, estado.
 *   - Campañas: las que se superponen con el período, y su situación.
 *
 * Tablas heredadas del curso (cupones, discounts): respetan el SoftDelete
 * (deleted_at IS NULL).
 */
class PromocionesReporteService extends ConsultaReporte
{
    private const TIPO_DESCUENTO = [1 => 'Porcentaje', 2 => 'Monto fijo'];
    private const ALCANCE        = [1 => 'Productos', 2 => 'Categorías', 3 => 'Marcas'];
    private const TIPO_CAMPANA   = [1 => 'Normal', 2 => 'Flash', 3 => 'Link'];

    // =====================================================================
    // 1. Cupones
    // =====================================================================

    public function cupones(): array
    {
        $filas = DB::table('cupones as c')
            ->whereNull('c.deleted_at')
            ->selectRaw('c.id, c.code, c.type_discount, c.discount, c.type_count, c.num_use,
                c.type_cupone, c.state, c.created_at,
                (SELECT COUNT(*) FROM cupone_products x   WHERE x.cupone_id = c.id AND x.deleted_at IS NULL) AS productos,
                (SELECT COUNT(*) FROM cupone_categories x WHERE x.cupone_id = c.id AND x.deleted_at IS NULL) AS categorias,
                (SELECT COUNT(*) FROM cupone_brands x     WHERE x.cupone_id = c.id AND x.deleted_at IS NULL) AS marcas')
            ->orderByDesc('c.created_at')
            ->get()
            ->map(fn ($r) => [
                'id'             => (int) $r->id,
                'codigo'         => $r->code,
                'tipo_descuento' => self::TIPO_DESCUENTO[$r->type_discount] ?? '—',
                'descuento'      => (float) $r->discount,
                'descuento_texto' => $this->textoDescuento((int) $r->type_discount, (float) $r->discount),
                'uso'            => (int) $r->type_count === 1 ? 'Ilimitado' : 'Hasta ' . (int) $r->num_use . ' usos',
                'alcance'        => self::ALCANCE[$r->type_cupone] ?? '—',
                'elementos'      => $this->elementos((int) $r->type_cupone, $r),
                'estado'         => (int) $r->state === 1 ? 'Activo' : 'Inactivo',
                'creado'         => substr((string) $r->created_at, 0, 10),
            ])
            ->all();

        return [
            'filas'   => $filas,
            'totales' => [
                'cupones'     => count($filas),
                'activos'     => count(array_filter($filas, fn ($x) => $x['estado'] === 'Activo')),
                'inactivos'   => count(array_filter($filas, fn ($x) => $x['estado'] === 'Inactivo')),
                'porcentaje'  => count(array_filter($filas, fn ($x) => $x['tipo_descuento'] === 'Porcentaje')),
                'monto_fijo'  => count(array_filter($filas, fn ($x) => $x['tipo_descuento'] === 'Monto fijo')),
                'ilimitados'  => count(array_filter($filas, fn ($x) => $x['uso'] === 'Ilimitado')),
            ],
        ];
    }

    // =====================================================================
    // 2. Campañas de descuento
    // =====================================================================

    /** Campañas cuya vigencia se superpone con el período filtrado. */
    public function campanas(FiltrosReporte $f): array
    {
        [$desde, $hasta] = $f->rangoFechas();
        $hoy = now()->toDateString();

        $filas = DB::table('discounts as d')
            ->whereNull('d.deleted_at')
            ->where('d.start_date', '<=', $hasta)
            ->where('d.end_date', '>=', $desde)
            ->selectRaw('d.id, d.code, d.type_discount, d.discount, d.start_date, d.end_date,
                d.discount_type, d.type_campaing, d.state,
                (d.end_date - d.start_date + 1) AS duracion,
                (SELECT COUNT(*) FROM discount_products x   WHERE x.discount_id = d.id AND x.deleted_at IS NULL) AS productos,
                (SELECT COUNT(*) FROM discount_categories x WHERE x.discount_id = d.id AND x.deleted_at IS NULL) AS categorias,
                (SELECT COUNT(*) FROM discount_brands x     WHERE x.discount_id = d.id AND x.deleted_at IS NULL) AS marcas')
            ->orderByDesc('d.start_date')
            ->get()
            ->map(fn ($r) => [
                'id'              => (int) $r->id,
                'codigo'          => $r->code,
                'tipo_campana'    => self::TIPO_CAMPANA[$r->type_campaing] ?? '—',
                'tipo_descuento'  => self::TIPO_DESCUENTO[$r->type_discount] ?? '—',
                'descuento'       => (float) $r->discount,
                'descuento_texto' => $this->textoDescuento((int) $r->type_discount, (float) $r->discount),
                'alcance'         => self::ALCANCE[$r->discount_type] ?? '—',
                'elementos'       => $this->elementos((int) $r->discount_type, $r),
                'inicio'          => $r->start_date,
                'fin'             => $r->end_date,
                'duracion'        => (int) $r->duracion,
                'situacion'       => $this->situacion((int) $r->state, $r->start_date, $r->end_date, $hoy),
            ])
            ->all();

        $contar = fn (string $s) => count(array_filter($filas, fn ($x) => $x['situacion'] === $s));

        return [
            'filas'   => $filas,
            'totales' => [
                'campanas'    => count($filas),
                'vigentes'    => $contar('vigente'),
                'programadas' => $contar('programada'),
                'finalizadas' => $contar('finalizada'),
                'inactivas'   => $contar('inactiva'),
                'flash'       => count(array_filter($filas, fn ($x) => $x['tipo_campana'] === 'Flash')),
            ],
        ];
    }

    // =====================================================================
    // Auxiliares
    // =====================================================================

    private function textoDescuento(int $tipo, float $valor): string
    {
        return $tipo === 1
            ? number_format($valor, $valor == (int) $valor ? 0 : 2, ',', '.') . ' %'
            : 'Gs. ' . number_format($valor, 0, ',', '.');
    }

    /** Cantidad de productos, categorías o marcas alcanzados según el tipo. */
    private function elementos(int $alcance, object $r): int
    {
        return (int) match ($alcance) {
            1       => $r->productos,
            2       => $r->categorias,
            3       => $r->marcas,
            default => 0,
        };
    }

    private function situacion(int $estado, string $inicio, string $fin, string $hoy): string
    {
        if ($estado !== 1) {
            return 'inactiva';
        }
        if ($hoy < $inicio) {
            return 'programada';
        }
        if ($hoy > $fin) {
            return 'finalizada';
        }

        return 'vigente';
    }
}
