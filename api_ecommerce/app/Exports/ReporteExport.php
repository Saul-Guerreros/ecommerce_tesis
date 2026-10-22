<?php

namespace App\Exports;

use App\Services\Reporte\Exportacion\FormatoReporte as T;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exportación genérica de cualquier vista de reporte.
 *
 * Estructura de la hoja:
 *   1    Título
 *   2    Empresa
 *   3..n Filtros aplicados + fecha de generación
 *   n+1  (vacía)
 *   n+2  Encabezados
 *   ...  Datos (números crudos con formato de celda)
 *   fin  Totales
 */
class ReporteExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithStyles, WithTitle
{
    private const FORMATOS = [
        T::GS     => '"Gs. "#,##0',
        T::ENTERO => '#,##0',
        T::NUMERO => '#,##0.##',
        T::PCT    => '0.0" %"',
    ];

    private readonly int $filaEncabezado;

    public function __construct(private readonly array $doc)
    {
        // Título + empresa + filtros + "Generado" + fila vacía
        $this->filaEncabezado = 2 + count($doc['filtros']) + 1 + 1 + 1;
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $this->doc['titulo']), 0, 31);
    }

    public function array(): array
    {
        $filas = [
            [$this->doc['titulo']],
            [$this->doc['empresa'] . ($this->doc['ruc'] ? ' — RUC ' . $this->doc['ruc'] : '')],
            ...array_map(fn ($l) => [$l], $this->doc['filtros']),
            ['Generado: ' . $this->doc['generado']],
            [''],   // separador: con [] Laravel Excel omite la fila y desplaza los estilos
            array_column($this->doc['columnas'], 'titulo'),
        ];

        foreach ($this->doc['filas'] as $fila) {
            $filas[] = array_map(
                fn ($c) => $this->celda($fila[$c['campo']] ?? null, $c['tipo']),
                $this->doc['columnas'],
            );
        }

        if ($this->doc['totales']) {
            $filas[] = array_map(
                fn ($v, $i) => $i === 0 ? $v : $this->celda($v, $this->doc['columnas'][$i]['tipo']),
                $this->doc['totales'],
                array_keys($this->doc['totales']),
            );
        }

        return $filas;
    }

    public function columnFormats(): array
    {
        $formatos = [];

        foreach ($this->doc['columnas'] as $i => $c) {
            if (isset(self::FORMATOS[$c['tipo']])) {
                $formatos[Coordinate::stringFromColumnIndex($i + 1)] = self::FORMATOS[$c['tipo']];
            }
        }

        return $formatos;
    }

    public function styles(Worksheet $sheet): array
    {
        $estilos = [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            $this->filaEncabezado => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE9ECEF']],
            ],
        ];

        if ($this->doc['totales']) {
            $estilos[$this->filaEncabezado + count($this->doc['filas']) + 1] = ['font' => ['bold' => true]];
        }

        return $estilos;
    }

    /** Números crudos (Excel los formatea); fechas y booleanos como texto legible. */
    private function celda(mixed $v, string $tipo): mixed
    {
        if ($v === null) {
            return $tipo === T::BOOL ? '' : null;
        }

        return match ($tipo) {
            T::GS, T::ENTERO, T::NUMERO, T::PCT => (float) $v,
            T::FECHA, T::BOOL, T::ETIQUETA       => T::valor($v, $tipo),
            default                              => $v,
        };
    }
}