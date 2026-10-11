<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Error de dominio: al confirmar el pago, el stock reservado ya no esta
 * disponible (tipicamente porque el producto se vendio en el local).
 *
 * Es una excepcion propia y no un abort(422) porque la respuesta tiene
 * que llevar la LISTA de productos faltantes para que el panel pueda
 * mostrarla linea por linea. Se lanza dentro de la transaccion, asi que
 * al propagarse revierte todo: el pago no queda confirmado y el stock no
 * se toca.
 *
 * 409 Conflict y no 422: el payload del administrador era valido, el
 * conflicto es con el estado actual del inventario.
 */
class StockInsuficienteException extends Exception
{
    /**
     * @param array<int, array{producto: string, requiere: float, disponible: float}> $faltantes
     */
    public function __construct(private array $faltantes)
    {
        parent::__construct('El stock reservado ya no está disponible.');
    }

    public function faltantes(): array
    {
        return $this->faltantes;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message'   => $this->getMessage(),
            'faltantes' => $this->faltantes,
        ], 409);
    }
}