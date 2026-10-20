<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\SucursalRequest;
use App\Http\Resources\Configuracion\SucursalResource;
use App\Models\Configuracion\Sucursal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SucursalController extends Controller
{
    private const DEPENDENCIAS = [
        'deposito' => ['columna' => 'dpo_sucid', 'etiqueta' => 'depósito(s)'],
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

            foreach ($conteos as $sucId => $total) {
                $usos[$sucId][$tabla] = (int) $total;
            }
        }

        return $usos;
    }

    // GET /api/admin/sucursales?search=&empresa=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = Sucursal::with(['empresa', 'ciudad']);

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(suc_nombre) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(suc_direccion) LIKE ?', [$search]);
            });
        }

        if ($request->filled('empresa')) {
            $query->where('suc_empid', $request->empresa);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('suc_estado', $request->estado);
        }

        $sucursales = $query->orderBy('suc_nombre')->get();
        $usos       = $this->contarUsos();

        $sucursales->each(function ($suc) use ($usos) {
            $detalle              = $usos[$suc->suc_id] ?? [];
            $suc->depositos_count = $detalle['deposito'] ?? 0;
        });

        return response()->json(['data' => SucursalResource::collection($sucursales)], 200);
    }

    // GET /api/admin/sucursales/config
    public function config(): JsonResponse
    {
        $empresas = DB::table('empresa')
            ->where('emp_estado', 1)
            ->orderBy('emp_razonsocial')
            ->get(['emp_id', 'emp_razonsocial']);

        $ciudades = DB::table('ciudad')
            ->join('departamento', 'ciu_depid', '=', 'dep_id')
            ->where('ciu_estado', 1)
            ->orderBy('dep_descri')
            ->orderBy('ciu_descri')
            ->get(['ciu_id', 'ciu_descri', 'dep_descri']);

        return response()->json([
            'empresas' => $empresas,
            'ciudades' => $ciudades,
        ], 200);
    }

    // GET /api/admin/sucursales/select
    public function select(): JsonResponse
    {
        $sucursales = DB::table('sucursal')
            ->join('empresa', 'suc_empid', '=', 'emp_id')
            ->where('suc_estado', 1)
            ->orderBy('emp_razonsocial')
            ->orderBy('suc_nombre')
            ->get(['suc_id', 'suc_empid', 'suc_nombre', 'emp_razonsocial']);

        return response()->json(['data' => $sucursales], 200);
    }

    // GET /api/admin/sucursales/{id}
    public function show(int $id): JsonResponse
    {
        $sucursal = Sucursal::with(['empresa', 'ciudad'])->find($id);

        if (!$sucursal) {
            return response()->json(['message' => 'Sucursal no encontrada'], 404);
        }

        return response()->json(['data' => new SucursalResource($sucursal)], 200);
    }

    // POST /api/admin/sucursales
        public function store(SucursalRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['suc_estado'] = $datos['suc_estado'] ?? 1;
        $datos['suc_princi'] = $datos['suc_princi'] ?? 0;
        // Visibilidad en la tienda web: activa por defecto. El formulario
        // siempre manda estos valores; el ?? cubre altas por API o seeders
        // que omitan los campos.
        $datos['suc_retiro']   = $datos['suc_retiro']   ?? true;
        $datos['suc_webstk']   = $datos['suc_webstk']   ?? true;
        $datos['suc_delivery'] = $datos['suc_delivery'] ?? true;

        $sucursal = Sucursal::create($datos);
        $sucursal->load(['empresa', 'ciudad']);

        return response()->json([
            'message' => 'Sucursal registrada correctamente',
            'data'    => new SucursalResource($sucursal),
        ], 201);
    }

    // PUT /api/admin/sucursales/{id}
    public function update(SucursalRequest $request, int $id): JsonResponse
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json(['message' => 'Sucursal no encontrada'], 404);
        }

        $sucursal->update($request->validated());
        $sucursal->load(['empresa', 'ciudad']);

        return response()->json([
            'message' => 'Sucursal actualizada correctamente',
            'data'    => new SucursalResource($sucursal),
        ], 200);
    }

    // DELETE /api/admin/sucursales/{id}
    public function destroy(int $id): JsonResponse
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json(['message' => 'Sucursal no encontrada'], 404);
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
                'message' => 'No se puede eliminar: la sucursal está siendo utilizada por '
                             . implode(', ', $detalle) . '. Desactívela en lugar de eliminarla.',
            ], 409);
        }

        $sucursal->delete();

        return response()->json(['message' => 'Sucursal eliminada correctamente'], 200);
    }

    // PATCH /api/admin/sucursales/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json(['message' => 'Sucursal no encontrada'], 404);
        }

        $sucursal->suc_estado = $sucursal->suc_estado == 1 ? 0 : 1;
        $sucursal->save();
        $sucursal->load(['empresa', 'ciudad']);

        return response()->json([
            'message' => $sucursal->suc_estado == 1
                ? 'Sucursal activada correctamente'
                : 'Sucursal desactivada correctamente',
            'data'    => new SucursalResource($sucursal),
        ], 200);
    }
}