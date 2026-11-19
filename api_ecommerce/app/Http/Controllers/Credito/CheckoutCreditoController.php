<?php

namespace App\Http\Controllers\Credito;

use App\Http\Controllers\Controller;
use App\Http\Requests\Credito\CheckoutCreditoConfirmarRequest;
use App\Models\Usuario;
use App\Services\Credito\CheckoutCreditoService;
use Illuminate\Http\JsonResponse;

/**
 * Checkout de una solicitud de crédito aceptada (etapa D).
 *
 *   GET  /tienda/credito/solicitudes/{id}/checkout            -> datos de los 2 pasos
 *   POST /tienda/credito/solicitudes/{id}/checkout/confirmar  -> crea el pedido
 */
class CheckoutCreditoController extends Controller
{
    public function __construct(
        private CheckoutCreditoService $servicio,
    ) {
        $this->middleware('auth:api');
    }

    public function contexto(int $id): JsonResponse
    {
        $usuario = $this->clienteAutenticado();

        return response()->json([
            'data' => $this->servicio->contexto($usuario, $id),
        ], 200);
    }

    public function confirmar(CheckoutCreditoConfirmarRequest $request, int $id): JsonResponse
    {
        $usuario = $this->clienteAutenticado();
        $pedido  = $this->servicio->confirmar($usuario, $id, $request->validated());

        return response()->json([
            'message' => 'Pedido a crédito creado. Ya reservamos tu producto.',
            'data'    => [
                'ped_id'     => $pedido->ped_id,
                'ped_numero' => $pedido->ped_numero,
                'ped_total'  => (float) $pedido->ped_total,
            ],
        ], 201);
    }

    private function clienteAutenticado(): Usuario
    {
        $usuario = auth('api')->user();
        $usuario->loadMissing('rol', 'persona');

        abort_if($usuario->rol?->rol_descri !== 'Cliente', 403, 'Este recurso es exclusivo para clientes.');

        return $usuario;
    }
}