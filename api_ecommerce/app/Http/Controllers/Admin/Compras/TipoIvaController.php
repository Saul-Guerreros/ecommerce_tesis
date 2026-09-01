<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\TipoIva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TipoIvaController extends Controller
{
    /**
     * Tablas que referencian al tipo de IVA. Se consultan con
     * Schema::hasTable porque se crean en fases posteriores.
     */
    private const DEPENDENCIAS = [
        'producto'       => ['columna' => 'prd_tivid', 'etiqueta' => 'producto(s)'],
        'compra_detalle' => ['columna' => 'cmd_tivid', 'etiqueta' => 'linea(s) de compra'],
    ];

    private function rules(?int $id = null): array
    {
        return [
            'tiv_descri' => [
                'required', 'string', 'max:45',
                Rule::unique('tipo_iva', 'tiv_descri')->ignore($id, 'tiv_id'),
            ],
            'tiv_tasa' => [
                'required', 'numeric', 'min:0', 'max:100',
                Rule::unique('tipo_iva', 'tiv_tasa')->ignore($id, 'tiv_id'),
            ],
            'tiv_estado' => 'nullable|integer|in:0,1',
        ];
    }

    private function messages(): array
    {
        return [
            'tiv_descri.required' => 'La descripcion es obligatoria.',
            'tiv_descri.unique'   => 'Ya existe un tipo de IVA con esa descripcion.',
            'tiv_descri.max'      => 'La descripcion no puede superar los 45 caracteres.',
            'tiv_tasa.required'   => 'La tasa es obligatoria.',
            'tiv_tasa.numeric'    => 'La tasa debe ser un valor numerico.',
            'tiv_tasa.unique'     => 'Ya existe un tipo de IVA con esa tasa.',
            'tiv_tasa.min'        => 'La tasa no puede ser negativa.',
            'tiv_tasa.max'        => 'La tasa no puede superar el 100%.',
        ];
    }

    /**
     * Cuenta los usos de un tipo de IVA en las tablas dependientes.
     * Devuelve el total y el detalle legible para el mensaje de bloqueo.
     */
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

    // GET /api/admin/tipos-iva?estado=
    public function index(Request $request): JsonResponse
    {
        $query = TipoIva::query();

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('tiv_estado', $request->estado);
        }

        $tipos = $query->orderBy('tiv_tasa')->get();

        $tipos->each(function ($tipo) {
            $tipo->usos_count = $this->contarUsos($tipo->tiv_id)['total'];
        });

        return response()->json(['data' => $tipos], 200);
    }

    /**
     * GET /api/admin/tipos-iva/select
     * Combo para el formulario de producto y las lineas de compra.
     */
    public function select(): JsonResponse
    {
        $tipos = TipoIva::where('tiv_estado', 1)
                        ->orderBy('tiv_tasa')
                        ->get(['tiv_id', 'tiv_descri', 'tiv_tasa']);

        return response()->json(['data' => $tipos], 200);
    }

    // GET /api/admin/tipos-iva/{id}
    public function show(int $id): JsonResponse
    {
        $tipo = TipoIva::find($id);

        if (!$tipo) {
            return response()->json(['message' => 'Tipo de IVA no encontrado'], 404);
        }

        return response()->json(['data' => $tipo], 200);
    }

    // POST /api/admin/tipos-iva
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $validator->validated();
        $datos['tiv_estado'] = $datos['tiv_estado'] ?? 1;

        $tipo = TipoIva::create($datos);

        return response()->json([
            'message' => 'Tipo de IVA registrado correctamente',
            'data'    => $tipo,
        ], 201);
    }

    // PUT /api/admin/tipos-iva/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $tipo = TipoIva::find($id);

        if (!$tipo) {
            return response()->json(['message' => 'Tipo de IVA no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($id), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $validator->validated();

        // Cambiar la tasa de un IVA ya usado alteraria el desglose de
        // documentos emitidos. Se permite corregir la descripcion, no la tasa.
        if ((float) $datos['tiv_tasa'] !== (float) $tipo->tiv_tasa) {
            $usos = $this->contarUsos($id);

            if ($usos['total'] > 0) {
                return response()->json([
                    'errors' => [
                        'tiv_tasa' => [
                            'No se puede cambiar la tasa: ya esta aplicada en '
                            . implode(', ', $usos['detalle'])
                            . '. Registre un nuevo tipo de IVA y desactive este.',
                        ],
                    ],
                ], 422);
            }
        }

        $tipo->update($datos);

        return response()->json([
            'message' => 'Tipo de IVA actualizado correctamente',
            'data'    => $tipo->fresh(),
        ], 200);
    }

    // DELETE /api/admin/tipos-iva/{id}
    public function destroy(int $id): JsonResponse
    {
        $tipo = TipoIva::find($id);

        if (!$tipo) {
            return response()->json(['message' => 'Tipo de IVA no encontrado'], 404);
        }

        $usos = $this->contarUsos($id);

        if ($usos['total'] > 0) {
            return response()->json([
                'message' => 'No se puede eliminar: el tipo de IVA esta aplicado en '
                             . implode(', ', $usos['detalle'])
                             . '. Desactivelo en lugar de eliminarlo.',
            ], 409);
        }

        $tipo->delete();

        return response()->json(['message' => 'Tipo de IVA eliminado correctamente'], 200);
    }

    // PATCH /api/admin/tipos-iva/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $tipo = TipoIva::find($id);

        if (!$tipo) {
            return response()->json(['message' => 'Tipo de IVA no encontrado'], 404);
        }

        $tipo->tiv_estado = $tipo->tiv_estado == 1 ? 0 : 1;
        $tipo->save();

        return response()->json([
            'message' => $tipo->tiv_estado == 1
                ? 'Tipo de IVA activado correctamente'
                : 'Tipo de IVA desactivado correctamente',
            'data'    => $tipo,
        ], 200);
    }
}
