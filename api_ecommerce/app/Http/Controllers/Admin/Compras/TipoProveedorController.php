<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\TipoProveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TipoProveedorController extends Controller
{
    /**
     * proveedor todavia no existe cuando se corre este modulo por primera
     * vez: Schema::hasTable evita el error hasta que llegue el paso 5.
     */
    private const DEPENDENCIAS = [
        'proveedor' => ['columna' => 'pro_tprid', 'etiqueta' => 'proveedor(es)'],
    ];

    private function rules(?int $id = null): array
    {
        return [
            'tpr_descri' => [
                'required', 'string', 'max:45',
                Rule::unique('tipo_proveedor', 'tpr_descri')->ignore($id, 'tpr_id'),
            ],
            'tpr_estado' => 'nullable|integer|in:0,1',
        ];
    }

    private function messages(): array
    {
        return [
            'tpr_descri.required' => 'La descripcion es obligatoria.',
            'tpr_descri.unique'   => 'Ya existe un tipo de proveedor con esa descripcion.',
            'tpr_descri.max'      => 'La descripcion no puede superar los 45 caracteres.',
        ];
    }

    private function contarUsos(int $id): array
    {
        $total   = 0;
        $detalle = [];

        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }

            $cantidad = DB::table($tabla)->where($info['columna'], $id)->count();

            if ($cantidad > 0) {
                $total    += $cantidad;
                $detalle[] = "{$cantidad} {$info['etiqueta']}";
            }
        }

        return ['total' => $total, 'detalle' => $detalle];
    }

    // GET /api/admin/tipos-proveedor?search=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = TipoProveedor::query();

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->whereRaw('LOWER(tpr_descri) LIKE ?', [$search]);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('tpr_estado', $request->estado);
        }

        $tipos = $query->orderBy('tpr_descri')->get();

        $tipos->each(function ($tipo) {
            $tipo->usos_count = $this->contarUsos($tipo->tpr_id)['total'];
        });

        return response()->json(['data' => $tipos], 200);
    }

    /**
     * GET /api/admin/tipos-proveedor/select
     * Combo para el formulario de proveedor (paso 5).
     */
    public function select(): JsonResponse
    {
        $tipos = TipoProveedor::where('tpr_estado', 1)
                              ->orderBy('tpr_descri')
                              ->get(['tpr_id', 'tpr_descri']);

        return response()->json(['data' => $tipos], 200);
    }

    // GET /api/admin/tipos-proveedor/{id}
    public function show(int $id): JsonResponse
    {
        $tipo = TipoProveedor::find($id);

        if (!$tipo) {
            return response()->json(['message' => 'Tipo de proveedor no encontrado'], 404);
        }

        return response()->json(['data' => $tipo], 200);
    }

    // POST /api/admin/tipos-proveedor
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $validator->validated();
        $datos['tpr_estado'] = $datos['tpr_estado'] ?? 1;

        $tipo = TipoProveedor::create($datos);

        return response()->json([
            'message' => 'Tipo de proveedor registrado correctamente',
            'data'    => $tipo,
        ], 201);
    }

    // PUT /api/admin/tipos-proveedor/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $tipo = TipoProveedor::find($id);

        if (!$tipo) {
            return response()->json(['message' => 'Tipo de proveedor no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($id), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tipo->update($validator->validated());

        return response()->json([
            'message' => 'Tipo de proveedor actualizado correctamente',
            'data'    => $tipo->fresh(),
        ], 200);
    }

    // DELETE /api/admin/tipos-proveedor/{id}
    public function destroy(int $id): JsonResponse
    {
        $tipo = TipoProveedor::find($id);

        if (!$tipo) {
            return response()->json(['message' => 'Tipo de proveedor no encontrado'], 404);
        }

        $usos = $this->contarUsos($id);

        if ($usos['total'] > 0) {
            return response()->json([
                'message' => 'No se puede eliminar: el tipo esta asignado a '
                             . implode(', ', $usos['detalle'])
                             . '. Desactivelo en lugar de eliminarlo.',
            ], 409);
        }

        $tipo->delete();

        return response()->json(['message' => 'Tipo de proveedor eliminado correctamente'], 200);
    }

    // PATCH /api/admin/tipos-proveedor/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $tipo = TipoProveedor::find($id);

        if (!$tipo) {
            return response()->json(['message' => 'Tipo de proveedor no encontrado'], 404);
        }

        // Desactivarlo solo lo saca del combo de alta: los proveedores que ya
        // lo tienen asignado siguen funcionando, por eso no se bloquea.
        $tipo->tpr_estado = $tipo->tpr_estado == 1 ? 0 : 1;
        $tipo->save();

        return response()->json([
            'message' => $tipo->tpr_estado == 1
                ? 'Tipo de proveedor activado correctamente'
                : 'Tipo de proveedor desactivado correctamente',
            'data'    => $tipo,
        ], 200);
    }
}
