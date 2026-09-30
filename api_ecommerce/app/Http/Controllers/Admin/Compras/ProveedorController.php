<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\Compra\ProveedorRequest;
use App\Http\Resources\Compra\ProveedorResource;
use App\Models\Compras\Proveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProveedorController extends Controller
{
    /**
     * 'contacto_proveedor' NO va aca: su FK es ON DELETE CASCADE (los
     * contactos no tienen existencia propia fuera del proveedor), asi que no
     * bloquea el borrado, se elimina junto con el proveedor. Solo se
     * bloquea por relaciones que representen documentos/historial real.
     */
    private const DEPENDENCIAS = [
        'compra_cab'         => ['columna' => 'com_proid', 'etiqueta' => 'compra(s) registrada(s)'],
        'timbrado_proveedor' => ['columna' => 'tpv_proid', 'etiqueta' => 'timbrado(s) registrado(s)'],
    ];

    private function contarUsos(): array
    {
        $usos = [];
        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) continue;
            $conteos = DB::table($tabla)
                ->select($info['columna'], DB::raw('COUNT(*) AS total'))
                ->groupBy($info['columna'])
                ->pluck('total', $info['columna']);
            foreach ($conteos as $id => $total) {
                $usos[$id][$tabla] = (int) $total;
            }
        }
        return $usos;
    }

    // GET /api/admin/proveedores?search=&tipo=&ciudad=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = Proveedor::with(['tipoProveedor', 'ciudad'])->withCount('contactos');

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(pro_razon) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(pro_nomfan) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(pro_ruc) LIKE ?', [$search]);
            });
        }

        if ($request->filled('tipo')) {
            $query->where('pro_tprid', $request->tipo);
        }

        if ($request->filled('ciudad')) {
            $query->where('pro_ciuid', $request->ciudad);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('pro_estado', $request->estado);
        }

        $proveedores = $query->orderBy('pro_razon')->get();

        return response()->json(['data' => ProveedorResource::collection($proveedores)], 200);
    }

    /**
     * GET /api/admin/proveedores/config
     * Tipos de proveedor y ciudades activas para los <select> del formulario.
     */
    public function config(): JsonResponse
    {
        $tipos = DB::table('tipo_proveedor')
            ->where('tpr_estado', 1)
            ->orderBy('tpr_descri')
            ->get(['tpr_id', 'tpr_descri']);

        $ciudades = DB::table('ciudad')
            ->join('departamento', 'ciu_depid', '=', 'dep_id')
            ->where('ciu_estado', 1)
            ->orderBy('dep_descri')
            ->orderBy('ciu_descri')
            ->get(['ciu_id', 'ciu_descri', 'dep_descri']);

        return response()->json([
            'tipos'    => $tipos,
            'ciudades' => $ciudades,
        ], 200);
    }

    // GET /api/admin/proveedores/select
    public function select(): JsonResponse
    {
        $proveedores = DB::table('proveedor')
            ->where('pro_estado', 1)
            ->orderBy('pro_razon')
            ->get(['pro_id', 'pro_razon', 'pro_ruc']);

        return response()->json(['data' => $proveedores], 200);
    }

    // GET /api/admin/proveedores/{id}
    public function show(int $id): JsonResponse
    {
        // withCount + with('contactos') porque el detalle SI necesita el
        // listado completo de contactos, no solo el conteo (index() se
        // conforma con el conteo para no traer datos de mas en la tabla).
        $proveedor = Proveedor::with(['tipoProveedor', 'ciudad', 'contactos'])->find($id);

        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        return response()->json(['data' => new ProveedorResource($proveedor)], 200);
    }

    // POST /api/admin/proveedores
    public function store(ProveedorRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['pro_estado'] = $datos['pro_estado'] ?? 1;

        $proveedor = Proveedor::create($datos);
        $proveedor->load(['tipoProveedor', 'ciudad']);

        return response()->json([
            'message' => 'Proveedor registrado correctamente',
            'data'    => new ProveedorResource($proveedor),
        ], 201);
    }

    // PUT /api/admin/proveedores/{id}
    public function update(ProveedorRequest $request, int $id): JsonResponse
    {
        $proveedor = Proveedor::find($id);
        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $proveedor->update($request->validated());
        $proveedor->load(['tipoProveedor', 'ciudad']);

        return response()->json([
            'message' => 'Proveedor actualizado correctamente',
            'data'    => new ProveedorResource($proveedor),
        ], 200);
    }

    // DELETE /api/admin/proveedores/{id}
    public function destroy(int $id): JsonResponse
    {
        $proveedor = Proveedor::find($id);
        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $detalle = [];
        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) continue;
            $total = DB::table($tabla)->where($info['columna'], $id)->count();
            if ($total > 0) $detalle[] = "{$total} {$info['etiqueta']}";
        }

        if (!empty($detalle)) {
            return response()->json([
                'message' => 'No se puede eliminar: el proveedor tiene '
                             . implode(', ', $detalle) . '. Desactívelo en lugar de eliminarlo.',
            ], 409);
        }

        // Sus contactos se eliminan en cascada por la FK (onDelete cascade).
        $proveedor->delete();

        return response()->json(['message' => 'Proveedor eliminado correctamente'], 200);
    }

    // PATCH /api/admin/proveedores/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $proveedor = Proveedor::find($id);
        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $proveedor->pro_estado = $proveedor->pro_estado == 1 ? 0 : 1;
        $proveedor->save();
        $proveedor->load(['tipoProveedor', 'ciudad']);

        return response()->json([
            'message' => $proveedor->pro_estado == 1 ? 'Proveedor activado correctamente' : 'Proveedor desactivado correctamente',
            'data'    => new ProveedorResource($proveedor),
        ], 200);
    }
}