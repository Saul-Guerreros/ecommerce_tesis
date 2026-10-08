<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cliente\DireccionClienteRequest;
use App\Http\Resources\Cliente\DireccionClienteResource;
use App\Models\Pedido\PedidoTemporal;
use App\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DireccionClienteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(): JsonResponse
    {
        $persona = $this->personaAutenticada();

        $direcciones = $persona->direcciones()
            ->activas()
            ->with('ciudad.departamento')
            ->orderByDesc('dir_predet')
            ->orderByDesc('dir_id')
            ->get();

        return response()->json([
            'data' => DireccionClienteResource::collection($direcciones),
        ]);
    }

    public function catalogos(): JsonResponse
    {
        $departamentos = DB::table('departamento')
            ->where('dep_estado', 1)
            ->orderBy('dep_descri')
            ->get(['dep_id', 'dep_descri']);

        $ciudades = DB::table('ciudad')
            ->where('ciu_estado', 1)
            ->orderBy('ciu_descri')
            ->get(['ciu_id', 'ciu_depid', 'ciu_descri']);

        return response()->json(compact('departamentos', 'ciudades'));
    }

    public function store(DireccionClienteRequest $request): JsonResponse
    {
        $persona = $this->personaAutenticada();
        $data = $request->validated();

        $direccion = DB::transaction(function () use ($persona, $data) {
            if (!empty($data['dir_predet'])) {
                $persona->direcciones()->activas()->update(['dir_predet' => 0]);
            } elseif (!$persona->direcciones()->activas()->exists()) {
                // Primera direccion del cliente: queda predeterminada sola.
                $data['dir_predet'] = 1;
            }

            return $persona->direcciones()->create($data);
        });

        return response()->json([
            'message' => 'Dirección registrada correctamente.',
            'data' => new DireccionClienteResource($direccion->load('ciudad.departamento')),
        ], 201);
    }

    public function update(DireccionClienteRequest $request, string $id): JsonResponse
    {
        $persona = $this->personaAutenticada();
        $direccion = $persona->direcciones()->activas()->findOrFail($id);
        $data = $request->validated();

        DB::transaction(function () use ($persona, $direccion, $data) {
            if (!empty($data['dir_predet'])) {
                $persona->direcciones()->activas()
                    ->where('dir_id', '<>', $direccion->dir_id)
                    ->update(['dir_predet' => 0]);
            }
            $direccion->update($data);
        });

        return response()->json([
            'message' => 'Dirección actualizada correctamente.',
            'data' => new DireccionClienteResource($direccion->fresh()->load('ciudad.departamento')),
        ]);
    }

    /**
     * Borrado LOGICO (dir_estado = 0), no fisico.
     *
     * pedido_entrega.pen_dirid referencia direccion con ON DELETE
     * RESTRICT: un DELETE sobre una direccion ya usada en un pedido
     * confirmado lanza el error 23503 de PostgreSQL y el cliente recibe
     * un 500 en lugar de un mensaje. Y tampoco corresponde borrarla en
     * cascada: la direccion de entrega es parte del historial del pedido
     * y debe quedar tal como estaba al momento de la compra.
     *
     * Marcarla inactiva resuelve las dos cosas: desaparece de la lista
     * del cliente y los pedidos viejos conservan su referencia intacta.
     * Para eso dir_estado ya existia en la tabla desde el inicio.
     */
    public function destroy(string $id): JsonResponse
    {
        $usuario = auth('api')->user();
        $persona = $this->personaAutenticada();
        $direccion = $persona->direcciones()->activas()->findOrFail($id);

        DB::transaction(function () use ($usuario, $persona, $direccion) {
            $direccion->update(['dir_estado' => 0, 'dir_predet' => 0]);

            // El cliente no puede quedarse sin predeterminada: si cayo
            // la que lo era, asciende la mas reciente de las que quedan.
            if (!$persona->direcciones()->activas()->where('dir_predet', 1)->exists()) {
                $persona->direcciones()->activas()
                    ->orderByDesc('dir_id')
                    ->first()
                    ?->update(['dir_predet' => 1]);
            }

            $this->limpiarCheckoutEnCurso((int) $usuario->usu_id, (int) $direccion->dir_id);
        });

        return response()->json(['message' => 'Dirección eliminada correctamente.']);
    }

    /**
     * Si el checkout en curso ya tenia guardada ESTA direccion en el
     * paso 1, hay que invalidar ese paso.
     *
     * ptm_entrega guarda dir_id y PedidoService lo copia tal cual a
     * pedido_entrega.pen_dirid al confirmar. Sin esta limpieza, el
     * cliente que borra su direccion y sigue hasta el paso 3 arrastra un
     * dir_id dado de baja hasta el momento de confirmar el pedido, que
     * es el peor lugar posible para fallar. Se lo devuelve al paso 1
     * para que elija otra.
     */
    private function limpiarCheckoutEnCurso(int $usuarioId, int $direccionId): void
    {
        $temporal = PedidoTemporal::where('ptm_usuid', $usuarioId)->first();

        if (!$temporal || (int) ($temporal->ptm_entrega['dir_id'] ?? 0) !== $direccionId) {
            return;
        }

        $temporal->update([
            'ptm_entrega' => null,
            'ptm_factura' => null,   // el paso 2 se valida contra el paso 1
            'ptm_paso'    => PedidoTemporal::PASO_ENTREGA,
        ]);
    }

    public function marcarPredeterminada(string $id): JsonResponse
    {
        $persona = $this->personaAutenticada();
        $direccion = $persona->direcciones()->activas()->findOrFail($id);

        DB::transaction(function () use ($persona, $direccion) {
            $persona->direcciones()->activas()->update(['dir_predet' => 0]);
            $direccion->update(['dir_predet' => 1]);
        });

        return response()->json([
            'message' => 'Dirección marcada como predeterminada.',
            'data' => new DireccionClienteResource($direccion->fresh()->load('ciudad.departamento')),
        ]);
    }

    private function personaAutenticada(): Persona
    {
        $usuario = auth('api')->user();
        $usuario->loadMissing('rol', 'persona');

        abort_if($usuario->rol?->rol_descri !== 'Cliente', 403, 'Este recurso es exclusivo para clientes.');

        return $usuario->persona;
    }
}