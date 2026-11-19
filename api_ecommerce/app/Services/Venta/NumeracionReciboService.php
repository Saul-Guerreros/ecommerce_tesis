<?php

namespace App\Services\Venta;

use Illuminate\Support\Facades\DB;

/**
 * Numeracion del recibo. Punto UNICO donde se toma un numero.
 *
 * Usa una secuencia de PostgreSQL en vez del lock que usa
 * NumeracionVentaService: nextval() es atomico y no bloquea, y aca no
 * hace falta el lock porque el recibo NO es un documento fiscal (no
 * consume timbrado). Un salto en la numeracion por una transaccion
 * abortada es aceptable en un recibo; en una factura no lo seria.
 */
class NumeracionReciboService
{
    private const PREFIJO = 'REC';
    private const DIGITOS = 6;

    /** Devuelve el proximo numero: REC-000001. */
    public function siguiente(): string
    {
        $numero = (int) DB::selectOne("SELECT nextval('recibo_numero_seq') AS n")->n;

        return self::PREFIJO . '-' . str_pad((string) $numero, self::DIGITOS, '0', STR_PAD_LEFT);
    }
}