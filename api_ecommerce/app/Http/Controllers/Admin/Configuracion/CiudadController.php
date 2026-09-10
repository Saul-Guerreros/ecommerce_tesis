<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\CiudadRequest;
use App\Http\Resources\Configuracion\CiudadResource;
use App\Models\Configuracion\Ciudad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CiudadController extends Controller
{
    /**
     * Tablas que referencian a ciudad. Se usan para impedir el borrado de una
     * ciudad en uso y para explicarle al usuario donde esta siendo utilizada.
     * Schema::hasTable() permite declarar dependencias futuras (proveedor)
     * sin romper mientras esas tablas no existan.
     */
    private const DEPENDENCIAS = [
        // 'calle' NO va acá: ya no tiene columna cal_ciuid propia (depende de
        // ciudad solo de forma transitiva, vía barrio). Si una ciudad tiene
        // barrios, esta entrada ya bloquea el borrado; y si un barrio tiene
        // calles, es BarrioController quien lo bloquea al intentar borrar
        // ese barrio. No hace falta (ni es correcto) repetir la validación acá.
        'barrio'    => ['columna' => 'bar_ciuid', 'etiqueta' => 'barrio(s)'],
        'sucursal'  => ['columna' => 'suc_ciuid', 'etiqueta' => 'sucursal(es)'],
        'proveedor' => ['columna' => 'pro_ciuid', 'etiqueta' => 'proveedor(es)'],
    ];

    private function contarUsos(): array
    {
        $usos = [];

        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }

            $conteos = DB::table($tabla)
                ->select($info['columna'], DB::raw('COUNT(*) AS total'))
                ->groupBy($info['columna'])
                ->pluck('total', $info['columna']);

            foreach ($conteos as $ciuId => $total) {
                $usos[$ciuId][$tabla] = (int) $total;
            }
        }

        return $usos;
    }

    // GET /api/admin/ciudades?search=&departamento=&estado=
    public function index(Request $request): JsonResponse
    {
        // Eager loading explicito: el Resource expone dep_descri a traves de
        // whenLoaded('departamento'). Sin el with() la relacion no se carga
        // y la columna llegaria vacia al listado.
        $query = Ciudad::with('departamento');

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->whereRaw('LOWER(ciu_descri) LIKE ?', [$search]);
        }

        if ($request->filled('departamento')) {
            $query->where('ciu_depid', $request->departamento);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('ciu_estado', $request->estado);
        }

        $ciudades = $query->orderBy('ciu_descri')->get();
        $usos     = $this->contarUsos();

        $ciudades->each(function ($ciu) use ($usos) {
            $detalle           = $usos[$ciu->ciu_id] ?? [];
            $ciu->usos_count   = array_sum($detalle);
            $ciu->usos_detalle = $detalle;
        });

        return response()->json(['data' => CiudadResource::collection($ciudades)], 200);
    }

    /**
     * GET /api/admin/ciudades/config
     * Departamentos para el <select> del formulario de ciudad.
     */
    public function config(): JsonResponse
    {
        $departamentos = DB::table('departamento')
            ->where('dep_estado', 1)
            ->orderBy('dep_descri')
            ->get(['dep_id', 'dep_descri']);

        return response()->json(['departamentos' => $departamentos], 200);
    }

    /**
     * GET /api/admin/ciudades/select
     * Lista plana para los combos de OTROS formularios (barrio, calle,
     * sucursal, proveedor). Solo ciudades activas: no tiene sentido asignar
     * una ciudad dada de baja.
     */
    public function select(): JsonResponse
    {
        $ciudades = DB::table('ciudad')
            ->join('departamento', 'ciu_depid', '=', 'dep_id')
            ->where('ciu_estado', 1)
            ->orderBy('dep_descri')
            ->orderBy('ciu_descri')
            ->get(['ciu_id', 'ciu_descri', 'dep_descri']);

        return response()->json(['data' => $ciudades], 200);
    }

    // GET /api/admin/ciudades/{id}
    public function show(int $id): JsonResponse
    {
        $ciudad = Ciudad::with('departamento')->find($id);

        if (!$ciudad) {
            return response()->json(['message' => 'Ciudad no encontrada'], 404);
        }

        return response()->json(['data' => new CiudadResource($ciudad)], 200);
    }

    // POST /api/admin/ciudades
    public function store(CiudadRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['ciu_estado'] = $datos['ciu_estado'] ?? 1;

        $ciudad = Ciudad::create($datos);

        return response()->json([
            'message' => 'Ciudad registrada correctamente',
            'data'    => new CiudadResource($ciudad),
        ], 201);
    }

    // PUT /api/admin/ciudades/{id}
    public function update(CiudadRequest $request, int $id): JsonResponse
    {
        $ciudad = Ciudad::find($id);

        if (!$ciudad) {
            return response()->json(['message' => 'Ciudad no encontrada'], 404);
        }

        $ciudad->update($request->validated());

        return response()->json([
            'message' => 'Ciudad actualizada correctamente',
            'data'    => new CiudadResource($ciudad->fresh()),
        ], 200);
    }

    // DELETE /api/admin/ciudades/{id}
    public function destroy(int $id): JsonResponse
    {
        $ciudad = Ciudad::find($id);

        if (!$ciudad) {
            return response()->json(['message' => 'Ciudad no encontrada'], 404);
        }

        $detalle = [];

        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }

            $total = DB::table($tabla)->where($info['columna'], $id)->count();

            if ($total > 0) {
                $detalle[] = "{$total} {$info['etiqueta']}";
            }
        }

        if (!empty($detalle)) {
            return response()->json([
                'message' => 'No se puede eliminar: la ciudad esta siendo utilizada por '
                             . implode(', ', $detalle) . '. Desactivela en lugar de eliminarla.',
            ], 409);
        }

        $ciudad->delete();

        return response()->json(['message' => 'Ciudad eliminada correctamente'], 200);
    }

    // PATCH /api/admin/ciudades/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $ciudad = Ciudad::find($id);

        if (!$ciudad) {
            return response()->json(['message' => 'Ciudad no encontrada'], 404);
        }

        $ciudad->ciu_estado = $ciudad->ciu_estado == 1 ? 0 : 1;
        $ciudad->save();

        return response()->json([
            'message' => $ciudad->ciu_estado == 1
                ? 'Ciudad activada correctamente'
                : 'Ciudad desactivada correctamente',
            'data'    => new CiudadResource($ciudad),
        ], 200);
    }
}
