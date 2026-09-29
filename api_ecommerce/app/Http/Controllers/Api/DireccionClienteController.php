<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cliente\DireccionClienteRequest;
use App\Http\Resources\Cliente\DireccionClienteResource;
use App\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Libreta de direcciones del cliente autenticado. Todo queda scopeado a
 * $persona->direcciones() para que un cliente jamás pueda leer/editar la
 * dirección de otro solo adivinando el {id} (IDOR).
 */
class DireccionClienteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /** GET /api/tienda/perfil/direcciones */
    public function index(): JsonResponse
    {
        $persona = $this->personaAutenticada();

        $direcciones = $persona->direcciones()
            ->with(['barrio.ciudad', 'calle'])
            ->orderByDesc('dir_predet')
            ->orderByDesc('dir_id')
            ->get();

        return response()->json([
            'data' => DireccionClienteResource::collection($direcciones),
        ]);
    }

    /**
     * GET /api/tienda/perfil/direcciones/catalogos
     * Ciudades + barrios + calles activos, para armar el select en cascada
     * Ciudad -> Barrio -> Calle en el formulario (mismo criterio que ya usa
     * CalleController::config() en el panel admin).
     */
    public function catalogos(): JsonResponse
    {
        $ciudades = DB::table('ciudad')
            ->join('departamento', 'ciu_depid', '=', 'dep_id')
            ->where('ciu_estado', 1)
            ->orderBy('dep_descri')
            ->orderBy('ciu_descri')
            ->get(['ciu_id', 'ciu_descri', 'dep_descri']);

        $barrios = DB::table('barrio')
            ->where('bar_estado', 1)
            ->orderBy('bar_descri')
            ->get(['bar_id', 'bar_ciuid', 'bar_descri']);

        $calles = DB::table('calle')
            ->where('cal_estado', 1)
            ->orderBy('cal_descri')
            ->get(['cal_id', 'cal_barid', 'cal_descri']);

        return response()->json(compact('ciudades', 'barrios', 'calles'));
    }

    /** POST /api/tienda/perfil/direcciones */
    public function store(DireccionClienteRequest $request): JsonResponse
    {
        $persona = $this->personaAutenticada();
        $data = $request->validated();

        $direccion = DB::transaction(function () use ($persona, $data) {
            if (!empty($data['dir_predet'])) {
                $persona->direcciones()->update(['dir_predet' => 0]);
            } elseif (!$persona->direcciones()->exists()) {
                // primera dirección del cliente: queda predeterminada sola
                $data['dir_predet'] = 1;
            }

            return $persona->direcciones()->create($data);
        });

        return response()->json([
            'message' => 'Dirección registrada correctamente.',
            'data' => new DireccionClienteResource($direccion->load('barrio.ciudad', 'calle')),
        ], 201);
    }

    /** PUT /api/tienda/perfil/direcciones/{id} */
    public function update(DireccionClienteRequest $request, string $id): JsonResponse
    {
        $persona = $this->personaAutenticada();
        $direccion = $persona->direcciones()->findOrFail($id);
        $data = $request->validated();

        DB::transaction(function () use ($persona, $direccion, $data) {
            if (!empty($data['dir_predet'])) {
                $persona->direcciones()->where('dir_id', '<>', $direccion->dir_id)->update(['dir_predet' => 0]);
            }
            $direccion->update($data);
        });

        return response()->json([
            'message' => 'Dirección actualizada correctamente.',
            'data' => new DireccionClienteResource($direccion->fresh()->load('barrio.ciudad', 'calle')),
        ]);
    }

    /** DELETE /api/tienda/perfil/direcciones/{id} */
    public function destroy(string $id): JsonResponse
    {
        $persona = $this->personaAutenticada();
        $direccion = $persona->direcciones()->findOrFail($id);
        $eraPredeterminada = (bool) $direccion->dir_predet;

        $direccion->delete();

        if ($eraPredeterminada) {
            $persona->direcciones()->orderByDesc('dir_id')->first()?->update(['dir_predet' => 1]);
        }

        return response()->json(['message' => 'Dirección eliminada correctamente.']);
    }

    /** PATCH /api/tienda/perfil/direcciones/{id}/predeterminada */
    public function marcarPredeterminada(string $id): JsonResponse
    {
        $persona = $this->personaAutenticada();
        $direccion = $persona->direcciones()->findOrFail($id);

        DB::transaction(function () use ($persona, $direccion) {
            $persona->direcciones()->update(['dir_predet' => 0]);
            $direccion->update(['dir_predet' => 1]);
        });

        return response()->json([
            'message' => 'Dirección marcada como predeterminada.',
            'data' => new DireccionClienteResource($direccion->fresh()->load('barrio.ciudad', 'calle')),
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