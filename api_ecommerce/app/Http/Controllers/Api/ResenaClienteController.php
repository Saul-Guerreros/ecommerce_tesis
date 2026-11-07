<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\ResenaClienteRequest;
use App\Http\Resources\Catalogo\ResenaClienteResource;
use App\Http\Resources\Pedido\ProductoResumenResource;
use App\Models\Catalogo\Resena;
use App\Models\Cliente;
use App\Models\Pedido\EstadoPedido;
use App\Models\Pedido\Pedido;
use App\Models\PublicacionWeb;
use App\Models\Usuario;
use App\Services\Catalogo\ResenaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reseñas del lado del cliente autenticado (auth:api).
 *
 *  - mia:       estado de la reseña del cliente para un producto.
 *  - store:     crear (solo con compra entregada).
 *  - update:    editar la propia.
 *  - destroy:   eliminar la propia.
 *  - porPedido: productos de un pedido entregado y si ya fueron calificados
 *               (botón "Calificar" de Mis Pedidos).
 */
class ResenaClienteController extends Controller
{
    public function __construct(private ResenaService $servicio)
    {
        $this->middleware('auth:api');
    }

    // GET /api/tienda/producto/{slug}/resenas/mia
    public function mia(string $slug): JsonResponse
    {
        $cliente = $this->clienteOpcional();

        if (! $cliente) {
            return $this->respuestaMia(false, 'Solo los clientes de la tienda pueden escribir reseñas.', null);
        }

        $pub    = $this->publicacion($slug);
        $grupo  = $this->servicio->grupoDe($pub->producto);
        $resena = $this->servicio->resenaDelCliente($cliente, $grupo);

        if ($resena) {
            $resena->load(['cliente.persona', 'producto.publicacionWeb']);

            return $this->respuestaMia(false, null, new ResenaClienteResource($resena));
        }

        $compro = $this->servicio->compraQueHabilita($cliente, $grupo) !== null;

        return $this->respuestaMia(
            $compro,
            $compro ? null : 'Podrás opinar cuando recibas un pedido con este producto.',
            null,
        );
    }

    // POST /api/tienda/producto/{slug}/resenas
    public function store(ResenaClienteRequest $request, string $slug): JsonResponse
    {
        $cliente = $this->clienteAutenticado();
        $pub     = $this->publicacion($slug);

        $resena = $this->servicio->crear($cliente, $pub->producto, $request->validated());
        $resena->load(['cliente.persona', 'producto.publicacionWeb']);

        return response()->json([
            'message' => '¡Gracias! Tu reseña ya está publicada.',
            'data'    => new ResenaClienteResource($resena),
            'resumen' => $this->servicio->resumen($resena->res_grpid),
        ], 201);
    }

    // PUT /api/tienda/resenas/{id}
    public function update(ResenaClienteRequest $request, int $id): JsonResponse
    {
        $resena = $this->resenaPropia($id);
        $resena = $this->servicio->actualizar($resena, $request->validated());
        $resena->load(['cliente.persona', 'producto.publicacionWeb']);

        return response()->json([
            'message' => 'Tu reseña se actualizó.',
            'data'    => new ResenaClienteResource($resena),
            'resumen' => $this->servicio->resumen($resena->res_grpid),
        ]);
    }

    // DELETE /api/tienda/resenas/{id}
    public function destroy(int $id): JsonResponse
    {
        $resena = $this->resenaPropia($id);
        $grupo  = $resena->res_grpid;

        $this->servicio->eliminar($resena);

        return response()->json([
            'message' => 'Tu reseña se eliminó.',
            'resumen' => $this->servicio->resumen($grupo),
        ]);
    }

    // GET /api/tienda/pedidos/{numero}/resenas
    public function porPedido(Request $request, string $numero): JsonResponse
    {
        $cliente = $this->clienteAutenticado();

        $pedido = Pedido::query()
            ->where('ped_numero', $numero)
            ->where('ped_cliid', $cliente->cli_id) // nunca el pedido de otro cliente
            ->with(['estadoPedido', 'detalles.producto.publicacionWeb.imagenes'])
            ->firstOrFail();

        if ($pedido->estadoPedido?->est_codigo !== EstadoPedido::ENTREGADO) {
            return response()->json(['habilitado' => false, 'data' => []]);
        }

        // Reseñas existentes del cliente para los grupos de este pedido.
        $grupos = $pedido->detalles
            ->filter(fn ($d) => $d->producto)
            ->mapWithKeys(fn ($d) => [$d->dpd_id => $this->servicio->grupoDe($d->producto)]);

        $resenas = Resena::where('res_cliid', $cliente->cli_id)
            ->whereIn('res_grpid', $grupos->unique()->values())
            ->get()
            ->keyBy('res_grpid');

        // Un ítem por modelo: si compró dos colores del mismo modelo se
        // califica una sola vez (la reseña es compartida).
        $items = $pedido->detalles
            ->filter(fn ($d) => $d->producto)
            ->unique(fn ($d) => $grupos[$d->dpd_id])
            ->map(function ($d) use ($grupos, $resenas, $request) {
                $pub    = $d->producto->publicacionWeb;
                $resena = $resenas->get($grupos[$d->dpd_id]);

                return [
                    'prd_id'       => (int) $d->dpd_prdid,
                    'nombre'       => $d->dpd_descri,
                    'imagen'       => (new ProductoResumenResource($d))->toArray($request)['imagen'],
                    'slug'         => $pub && $pub->estaPublicada() ? $pub->pub_slug : null,
                    'resenada'     => $resena !== null,
                    'calificacion' => $resena?->res_califi,
                ];
            })
            ->values();

        return response()->json(['habilitado' => true, 'data' => $items]);
    }

    // ---------------------------------------------------------------

    private function respuestaMia(bool $puede, ?string $motivo, ?ResenaClienteResource $resena): JsonResponse
    {
        return response()->json([
            'data' => [
                'puede_resenar' => $puede,
                'motivo'        => $motivo,
                'resena'        => $resena,
            ],
        ]);
    }

    private function publicacion(string $slug): PublicacionWeb
    {
        return PublicacionWeb::publicadas()
            ->where('pub_slug', $slug)
            ->with('producto')
            ->firstOrFail();
    }

    /** 404 (no 403) si la reseña es de otro cliente: no se revela que existe. */
    private function resenaPropia(int $id): Resena
    {
        return Resena::where('res_id', $id)
            ->where('res_cliid', $this->clienteAutenticado()->cli_id)
            ->firstOrFail();
    }

    private function clienteOpcional(): ?Cliente
    {
        /** @var Usuario $usuario */
        $usuario = auth('api')->user();
        $usuario->loadMissing('rol', 'persona.cliente');

        if ($usuario->rol?->rol_descri !== 'Cliente') {
            return null;
        }

        return $usuario->persona?->cliente;
    }

    private function clienteAutenticado(): Cliente
    {
        $cliente = $this->clienteOpcional();
        abort_if(! $cliente, 403, 'Este recurso es exclusivo para clientes.');

        return $cliente;
    }
}