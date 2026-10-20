<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Error de dominio al emitir una factura de venta: no hay timbrado
 * vigente, se agoto el rango de numeracion, o falta la caja del punto de
 * expedicion.
 *
 * Es una excepcion propia y no un abort(422) por dos motivos:
 *
 *   1. Se lanza DENTRO de la transaccion de confirmacion de pago, asi que
 *      al propagarse revierte todo. El pago no queda confirmado y el
 *      stock no se descuenta. Es el comportamiento correcto: una venta
 *      cerrada sin factura es peor que un pago que hay que reintentar
 *      despues de cargar el timbrado.
 *   2. El mensaje tiene que llegarle al administrador tal cual, porque le
 *      dice exactamente que configuracion le falta.
 *
 * 409 Conflict y no 422: el payload del administrador era valido; el
 * conflicto es con el estado actual de la configuracion fiscal.
 */
class EmisionFacturaException extends Exception
{
    public function __construct(string $mensaje)
    {
        parent::__construct($mensaje);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}