<?php

namespace App\Services\Reporte\Exportacion;

/**
 * Formato de presentación de cada tipo de columna en el PDF (es-PY).
 * En Excel los números viajan crudos y se formatean con formatos de celda,
 * para que el usuario pueda seguir calculando con ellos.
 */
final class FormatoReporte
{
    public const TEXTO    = 'texto';
    public const ETIQUETA = 'etiqueta'; // texto con la primera letra en mayúscula
    public const GS       = 'gs';
    public const NUMERO   = 'numero';
    public const ENTERO   = 'entero';
    public const PCT      = 'pct';
    public const FECHA    = 'fecha';
    public const BOOL     = 'bool';

    /** Tipos que se alinean a la derecha. */
    public const NUMERICOS = [self::GS, self::NUMERO, self::ENTERO, self::PCT];

    public static function valor(mixed $v, string $tipo): string
    {
        if ($v === null || $v === '') {
            return $tipo === self::BOOL ? '' : '—';
        }

        return match ($tipo) {
            self::GS       => 'Gs. ' . number_format((float) $v, 0, ',', '.'),
            self::ENTERO   => number_format((float) $v, 0, ',', '.'),
            self::NUMERO   => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 2, ',', '.'),
            self::PCT      => number_format((float) $v, 1, ',', '.') . ' %',
            self::FECHA    => date('d/m/Y', strtotime((string) $v)),
            self::BOOL     => $v ? 'Sí' : '',
            self::ETIQUETA => ucfirst((string) $v),
            default        => (string) $v,
        };
    }
}