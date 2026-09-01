<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\TipoComprobante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TipoComprobanteController extends Controller
{
    /**
     * Tablas que referencian al tipo de comprobante. timbrado ya existe;
     * las demas se crean en fases posteriores, de ahi el Schema::hasTable.
     */
    private const DEPENDENCIAS = [
        'timbrado'   => ['columna' => 'tim_tcoid', 'etiqueta' => 'timbrado(s)'],
        'compra_cab' => ['columna' => 'com_tcoid', 'etiqueta' => 'compra(s)'],
    ];

    private function rules(?int $id = null): array
    {
        return [
            'tco_codigo' => [
                'required', 'string', 'size:3', 'regex:/^[0-9]{3}$/',
                Rule::unique('tipo_comprobante', 'tco_codigo')->ignore($id, 'tco_id'),
            ],
            'tco_descri' => [
                'required', 'string', 'max:45',
                Rule::unique('tipo_comprobante', 'tco_descri')->ignore($id, 'tco_id'),
            ],
            'tco_reqtim'  => 'nullable|integer|in:0,1',
            'tco_afecstk' => 'nullable|integer|in:-1,0,1',
            'tco_estado'  => 'nullable|integer|in:0,1',
        ];
    }

    private function messages(): array
    {
        return [
            'tco_codigo.required' => 'El codigo del comprobante es obligatorio.',
            'tco_codigo.size'     => 'El codigo debe tener exactamente 3 digitos.',
            'tco_codigo.regex'    => 'El codigo debe ser numerico de 3 digitos (ej: 001).',
            'tco_codigo.unique'   => 'Ya existe un tipo de comprobante con ese codigo.',
            'tco_descri.required' => 'La descripcion es obligatoria.',
            'tco_descri.unique'   => 'Ya existe un tipo de comprobante con esa descripcion.',
            'tco_descri.max'      => 'La descripcion no puede superar los 45 caracteres.',
            'tco_afecstk.in'      => 'El efecto sobre el stock debe ser suma, resta o neutro.',
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

    // GET /api/admin/tipos-comprobante?search=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = TipoComprobante::query();

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(tco_descri) LIKE ?', [$search])
                  ->orWhere('tco_codigo', 'LIKE', $search);
            });
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('tco_estado', $request->estado);
        }

        $tipos = $query->orderBy('tco_codigo')->get();

        $tipos->each(function ($tipo) {
            $tipo->usos_count = $this->contarUsos($tipo->tco_id)['total'];
        });

        return response()->json(['data' => $tipos], 200);
    }

    /**
     * GET /api/admin/tipos-comprobante/select?reqtim=&afecstk=
     * Combo para timbrado (fase 2) y para la cabecera de compra (fase 5).
     * Los filtros permiten pedir, por ejemplo, solo los que suman stock.
     */
    public function select(Request $request): JsonResponse
    {
        $query = TipoComprobante::where('tco_estado', 1);

        if ($request->filled('reqtim')) {
            $query->where('tco_reqtim', $request->reqtim);
        }

        if ($request->filled('afecstk')) {
            $query->where('tco_afecstk', $request->afecstk);
        }

        $tipos = $query->orderBy('tco_codigo')
                       ->get(['tco_id', 'tco_codigo', 'tco_descri', 'tco_reqtim', 'tco_afecstk']);

        return response()->json(['data' => $tipos], 200);
    }

    // GET /api/admin/tipos-comprobante/{id}
    public function show(int $id): JsonResponse
    {
        $tipo = TipoComprobante::find($id);

        if (!$tipo) {
            return response()->json(['message' => 'Tipo de comprobante no encontrado'], 404);
        }

        return response()->json(['data' => $tipo], 200);
    }

    // POST /api/admin/tipos-comprobante
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $validator->validated();
        $datos['tco_reqtim']  = $datos['tco_reqtim']  ?? 1;
        $datos['tco_afecstk'] = $datos['tco_afecstk'] ?? 0;
        $datos['tco_estado']  = $datos['tco_estado']  ?? 1;

        $tipo = TipoComprobante::create($datos);

        return response()->json([
            'message' => 'Tipo de comprobante registrado correctamente',
            'data'    => $tipo,
        ], 201);
    }

    // PUT /api/admin/tipos-comprobante/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $tipo = TipoComprobante::find($id);

        if (!$tipo) {
            return response()->json(['message' => 'Tipo de comprobante no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($id), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $validator->validated();

        // El efecto sobre el stock define el signo de los movimientos ya
        // generados: cambiarlo despues descuadraria el inventario historico.
        if (isset($datos['tco_afecstk']) && (int) $datos['tco_afecstk'] !== $tipo->tco_afecstk) {
            $usos = $this->contarUsos($id);

            if ($usos['total'] > 0) {
                return response()->json([
                    'errors' => [
                        'tco_afecstk' => [
                            'No se puede cambiar el efecto sobre el stock: el comprobante ya se usa en '
                            . implode(', ', $usos['detalle']) . '.',
                        ],
                    ],
                ], 422);
            }
        }

        $tipo->update($datos);

        return response()->json([
            'message' => 'Tipo de comprobante actualizado correctamente',
            'data'    => $tipo->fresh(),
        ], 200);
    }

    // DELETE /api/admin/tipos-comprobante/{id}
    public function destroy(int $id): JsonResponse
    {
        $tipo = TipoComprobante::find($id);

        if (!$tipo) {
            return response()->json(['message' => 'Tipo de comprobante no encontrado'], 404);
        }

        $usos = $this->contarUsos($id);

        if ($usos['total'] > 0) {
            return response()->json([
                'message' => 'No se puede eliminar: el tipo de comprobante esta usado en '
                             . implode(', ', $usos['detalle'])
                             . '. Desactivelo en lugar de eliminarlo.',
            ], 409);
        }

        $tipo->delete();

        return response()->json(['message' => 'Tipo de comprobante eliminado correctamente'], 200);
    }

    // PATCH /api/admin/tipos-comprobante/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $tipo = TipoComprobante::find($id);

        if (!$tipo) {
            return response()->json(['message' => 'Tipo de comprobante no encontrado'], 404);
        }

        // Desactivar un comprobante con timbrados activos dejaria talonarios
        // vigentes que no se pueden usar.
        if ($tipo->tco_estado == 1 && Schema::hasTable('timbrado')) {
            $activos = DB::table('timbrado')
                         ->where('tim_tcoid', $id)
                         ->where('tim_estado', 1)
                         ->count();

            if ($activos > 0) {
                return response()->json([
                    'message' => "No se puede desactivar: hay {$activos} timbrado(s) activo(s) para este comprobante.",
                ], 409);
            }
        }

        $tipo->tco_estado = $tipo->tco_estado == 1 ? 0 : 1;
        $tipo->save();

        return response()->json([
            'message' => $tipo->tco_estado == 1
                ? 'Tipo de comprobante activado correctamente'
                : 'Tipo de comprobante desactivado correctamente',
            'data'    => $tipo,
        ], 200);
    }
}
