<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\Compra\TimbradoProveedorRequest;
use App\Http\Resources\Compra\TimbradoProveedorResource;
use App\Models\Compras\TimbradoProveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimbradoProveedorController extends Controller
{
    /**
     * Tablas que referencian al timbrado. Si esta usado, no se edita ni se
     * elimina: cambiar su vigencia podria dejar compras ya registradas
     * fuera de rango. Solo se permite desactivarlo.
     */
    private const DEPENDENCIAS = [
        'compra_cab' => ['columna' => 'com_tpvid', 'etiqueta' => 'compra(s) registrada(s)'],
    ];

    private function contarUsos(int $id): array
    {
        $detalle = [];
        foreach (self::DEPENDENCIAS as $tabla => $info) {
            $total = DB::table($tabla)->where($info['columna'], $id)->count();
            if ($total > 0) $detalle[] = "{$total} {$info['etiqueta']}";
        }
        return $detalle;
    }

    // GET /api/admin/timbrados-proveedor/config
    public function config(): JsonResponse
    {
        // Solo tipos que exigen timbrado: son los unicos que lo necesitan.
        $tipos = DB::table('tipo_comprobante')
            ->where('tco_estado', 1)
            ->where('tco_reqtim', 1)
            ->orderBy('tco_descri')
            ->get(['tco_id', 'tco_descri']);

        return response()->json(['tipos_comprobante' => $tipos], 200);
    }

    /**
     * GET /api/admin/timbrados-proveedor?proveedor=&tipo=&estado=
     * Lo usan el formulario de Proveedor (todos) y el de Compra (activos
     * de un tipo).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'proveedor' => ['required', 'integer', 'exists:proveedor,pro_id'],
            'tipo'      => ['nullable', 'integer'],
            'estado'    => ['nullable', 'in:0,1'],
        ]);

        $query = TimbradoProveedor::with('tipoComprobante')
            ->withCount('compras')
            ->where('tpv_proid', $request->proveedor);

        if ($request->filled('tipo')) {
            $query->where('tpv_tcoid', $request->tipo);
        }

        if ($request->filled('estado')) {
            $query->where('tpv_estado', $request->estado);
        }

        $timbrados = $query->orderByDesc('tpv_fechfn')->get();

        return response()->json(['data' => TimbradoProveedorResource::collection($timbrados)], 200);
    }

    // POST /api/admin/timbrados-proveedor
    public function store(TimbradoProveedorRequest $request): JsonResponse
    {
        $timbrado = TimbradoProveedor::create($request->validated() + ['tpv_estado' => 1]);
        $timbrado->load('tipoComprobante')->loadCount('compras');

        return response()->json([
            'message' => 'Timbrado registrado correctamente.',
            'data'    => new TimbradoProveedorResource($timbrado),
        ], 201);
    }

    // PUT /api/admin/timbrados-proveedor/{id}
    public function update(TimbradoProveedorRequest $request, int $id): JsonResponse
    {
        $timbrado = TimbradoProveedor::find($id);
        if (!$timbrado) {
            return response()->json(['message' => 'Timbrado no encontrado'], 404);
        }

        // Regla de negocio que depende del estado del sistema -> controlador.
        $usos = $this->contarUsos($id);
        if (!empty($usos)) {
            return response()->json([
                'message' => 'No se puede editar: el timbrado tiene ' . implode(', ', $usos)
                           . '. Desactívelo y registre uno nuevo si hay un error.',
            ], 409);
        }

        // El proveedor dueño no se cambia en una edicion.
        $timbrado->update($request->safe()->except('tpv_proid'));
        $timbrado->load('tipoComprobante')->loadCount('compras');

        return response()->json([
            'message' => 'Timbrado actualizado correctamente.',
            'data'    => new TimbradoProveedorResource($timbrado),
        ], 200);
    }

    // DELETE /api/admin/timbrados-proveedor/{id}
    public function destroy(int $id): JsonResponse
    {
        $timbrado = TimbradoProveedor::find($id);
        if (!$timbrado) {
            return response()->json(['message' => 'Timbrado no encontrado'], 404);
        }

        $usos = $this->contarUsos($id);
        if (!empty($usos)) {
            return response()->json([
                'message' => 'No se puede eliminar: el timbrado tiene ' . implode(', ', $usos)
                           . '. Desactívelo en lugar de eliminarlo.',
            ], 409);
        }

        $timbrado->delete();

        return response()->json(['message' => 'Timbrado eliminado correctamente.'], 200);
    }

    // PATCH /api/admin/timbrados-proveedor/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $timbrado = TimbradoProveedor::find($id);
        if (!$timbrado) {
            return response()->json(['message' => 'Timbrado no encontrado'], 404);
        }

        $timbrado->tpv_estado = $timbrado->tpv_estado === 1 ? 0 : 1;
        $timbrado->save();
        $timbrado->load('tipoComprobante')->loadCount('compras');

        return response()->json([
            'message' => $timbrado->tpv_estado === 1 ? 'Timbrado activado correctamente.' : 'Timbrado desactivado correctamente.',
            'data'    => new TimbradoProveedorResource($timbrado),
        ], 200);
    }
}