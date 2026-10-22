<?php

namespace App\Services\Reporte\Exportacion;

use App\Exports\ReporteExport;
use App\Models\Configuracion\Empresa;
use App\Services\Reporte\FiltrosReporte;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Genera el documento exportable (PDF o Excel) de una vista de reporte.
 * Arma un "documento" neutral (título, filtros, columnas, filas, totales)
 * que luego se renderiza en el formato pedido.
 */
class ExportacionReporteService
{
    /** dompdf es lento con tablas muy grandes: por encima de esto, Excel. */
    private const MAX_FILAS_PDF = 2000;

    public function __construct(private readonly CatalogoExportacion $catalogo)
    {
    }

    public function descargar(string $reporte, string $vista, string $formato, FiltrosReporte $f, array $extra): Response
    {
        $doc = $this->documento($reporte, $vista, $f, $extra);

        $archivo = sprintf('reporte_%s_%s_%s', $reporte, str_replace('-', '_', $vista), now()->format('Ymd_His'));

        if ($formato === 'pdf') {
            if (count($doc['filas']) > self::MAX_FILAS_PDF) {
                throw ValidationException::withMessages([
                    'formato' => sprintf('El reporte tiene %d filas. Para más de %d, exportá en Excel o aplicá más filtros.',
                        count($doc['filas']), self::MAX_FILAS_PDF),
                ]);
            }

            return Pdf::loadView('pdf.reportes.reporte', ['doc' => $doc])
                ->setPaper('a4', $doc['orientacion'])
                ->download("{$archivo}.pdf");
        }

        return Excel::download(new ReporteExport($doc), "{$archivo}.xlsx");
    }

    private function documento(string $reporte, string $vista, FiltrosReporte $f, array $extra): array
    {
        $def     = $this->catalogo->resolver($reporte, $vista, $f, $extra);
        $totales = $def['resultado']['totales'] ?? [];

        // Fila de totales alineada con las columnas: "Total" en la primera.
        $filaTotales = null;
        if (collect($def['columnas'])->contains(fn ($c) => $c['total'] !== null)) {
            $filaTotales = array_map(
                fn ($c, $i) => $i === 0 ? 'Total' : ($c['total'] ? ($totales[$c['total']] ?? null) : null),
                $def['columnas'],
                array_keys($def['columnas']),
            );
        }

        $empresa = Empresa::query()->first();

        return [
            'titulo'      => $def['titulo'],
            'empresa'     => $empresa?->emp_razonsocial ?? config('app.name'),
            'ruc'         => $empresa?->emp_ruc,
            'filtros'     => $this->describirFiltros($f, $extra, $def),
            'generado'    => now()->format('d/m/Y H:i'),
            'usuario'     => auth('api')->user()?->usu_login,
            'orientacion' => $def['orientacion'],
            'columnas'    => $def['columnas'],
            'filas'       => $def['resultado']['filas'],
            'totales'     => $filaTotales,
        ];
    }

    /** Filtros aplicados en lenguaje legible, para el encabezado del documento. */
    private function describirFiltros(FiltrosReporte $f, array $extra, array $def): array
    {
        $lineas = [];

        $lineas[] = $def['usa_fechas']
            ? 'Período: ' . $f->desde->format('d/m/Y') . ' al ' . $f->hasta->format('d/m/Y')
            : 'Situación al ' . now()->format('d/m/Y H:i');

        if ($def['usa_sucursal']) {
            $lineas[] = 'Sucursal: ' . ($f->sucursalId
                ? DB::table('sucursal')->where('suc_id', $f->sucursalId)->value('suc_nombre')
                : 'Todas');
        }

        if ($f->depositoId) {
            $lineas[] = 'Depósito: ' . DB::table('deposito')->where('dpo_id', $f->depositoId)->value('dpo_nombre');
        }
        if (!empty($extra['categoria_id'])) {
            $lineas[] = 'Categoría: ' . DB::table('categoria')->where('cat_id', $extra['categoria_id'])->value('cat_nombre')
                . ' (incluye subcategorías)';
        }
        if (!empty($extra['marca_id'])) {
            $lineas[] = 'Marca: ' . DB::table('marca')->where('mar_id', $extra['marca_id'])->value('mar_descri');
        }
        if (!empty($extra['proveedor_id'])) {
            $lineas[] = 'Proveedor: ' . DB::table('proveedor')->where('pro_id', $extra['proveedor_id'])->value('pro_razon');
        }
        if (!empty($extra['tipo_dispositivo_id'])) {
            $lineas[] = 'Tipo de dispositivo: ' . DB::table('tipo_dispositivo')
                ->where('tdi_id', $extra['tipo_dispositivo_id'])->value('tdi_descri');
        }
        if (!empty($extra['tipo_cliente_id'])) {
            $lineas[] = 'Tipo de cliente: ' . DB::table('tipo_cliente')
                ->where('tpc_id', $extra['tipo_cliente_id'])->value('tpc_descri');
        }
        return [...$lineas, ...$def['notas']];
    }
}