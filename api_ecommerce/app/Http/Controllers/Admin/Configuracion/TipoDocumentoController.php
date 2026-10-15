<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\TipoDocumentoRequest;
use App\Http\Resources\Configuracion\TipoDocumentoResource;
use App\Models\TipoDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TipoDocumentoController extends Controller
{
    /** Tablas que referencian a tipo_documento (para "en uso" y bloqueo de borrado). */
    private const DEPENDENCIAS = [
        'persona'     => ['columna' => 'per_tpdid',  'etiqueta' => 'persona(s)'],
        'dato_fiscal' => ['columna' => 'dfi_tdocid', 'etiqueta' => 'dato(s) fiscal(es)'],
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

    // GET /api/admin/tipos-documento?search=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = TipoDocumento::query();

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(tpd_codigo) LIKE ?', [$search])
                                       ->orWhereRaw('LOWER(tpd_descri) LIKE ?', [$search]));
        }

        if ($request->filled('estado')) {
            $query->where('tpd_estado', $request->estado);
        }

        $tipos = $query->orderBy('tpd_descri')->get();
        $usos  = $this->contarUsos();

        $tipos->each(function ($t) use ($usos) {
            $t->usos_count = array_sum($usos[$t->tpd_id] ?? []);
        });

        return response()->json(['data' => TipoDocumentoResource::collection($tipos)], 200);
    }

    // GET /api/admin/tipos-documento/select
    public function select(): JsonResponse
    {
        $tipos = TipoDocumento::where('tpd_estado', 1)
            ->orderBy('tpd_descri')
            ->get(['tpd_id', 'tpd_codigo', 'tpd_descri']);

        return response()->json(['data' => $tipos], 200);
    }

    // GET /api/admin/tipos-documento/{id}
    public function show(int $id): JsonResponse
    {
        $tipo = TipoDocumento::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de documento no encontrado'], 404);

        return response()->json(['data' => new TipoDocumentoResource($tipo)], 200);
    }

    // POST /api/admin/tipos-documento
    public function store(TipoDocumentoRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['tpd_estado'] = $datos['tpd_estado'] ?? 1;

        $tipo = TipoDocumento::create($datos);

        return response()->json([
            'message' => 'Tipo de documento registrado correctamente',
            'data'    => new TipoDocumentoResource($tipo->fresh()),
        ], 201);
    }

    // PUT /api/admin/tipos-documento/{id}
    public function update(TipoDocumentoRequest $request, int $id): JsonResponse
    {
        $tipo = TipoDocumento::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de documento no encontrado'], 404);

        $datos = $request->validated();

        // CI y RUC los busca el sistema por código: solo se puede editar la descripción.
        if ($tipo->esDelSistema()) {
            $errores = [];

            if ($datos['tpd_codigo'] !== $tipo->tpd_codigo) {
                $errores['tpd_codigo'] = ["El código {$tipo->tpd_codigo} lo usa el sistema y no se puede cambiar."];
            }
            if (isset($datos['tpd_estado']) && (int) $datos['tpd_estado'] === 0) {
                $errores['tpd_estado'] = ["El tipo {$tipo->tpd_codigo} lo usa el sistema y no se puede desactivar."];
            }

            if ($errores) {
                return response()->json([
                    'message' => 'No se pueden aplicar los cambios a un tipo de documento del sistema.',
                    'errors'  => $errores,
                ], 422);
            }
        }

        $tipo->update($datos);

        return response()->json([
            'message' => 'Tipo de documento actualizado correctamente',
            'data'    => new TipoDocumentoResource($tipo->fresh()),
        ], 200);
    }

    // DELETE /api/admin/tipos-documento/{id}
    public function destroy(int $id): JsonResponse
    {
        $tipo = TipoDocumento::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de documento no encontrado'], 404);

        if ($tipo->esDelSistema()) {
            return response()->json([
                'message' => "El tipo {$tipo->tpd_codigo} lo usa el sistema y no se puede eliminar.",
            ], 409);
        }

        $detalle = [];
        foreach (self::DEPENDENCIAS as $tabla => $info) {
            if (!Schema::hasTable($tabla)) continue;
            $total = DB::table($tabla)->where($info['columna'], $id)->count();
            if ($total > 0) $detalle[] = "{$total} {$info['etiqueta']}";
        }

        if (!empty($detalle)) {
            return response()->json([
                'message' => 'No se puede eliminar: el tipo de documento está siendo utilizado por '
                             . implode(', ', $detalle) . '. Desactívelo en lugar de eliminarlo.',
            ], 409);
        }

        $tipo->delete();

        return response()->json(['message' => 'Tipo de documento eliminado correctamente'], 200);
    }

    // PATCH /api/admin/tipos-documento/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $tipo = TipoDocumento::find($id);
        if (!$tipo) return response()->json(['message' => 'Tipo de documento no encontrado'], 404);

        if ($tipo->esDelSistema() && $tipo->tpd_estado == 1) {
            return response()->json([
                'message' => "El tipo {$tipo->tpd_codigo} lo usa el sistema y no se puede desactivar.",
            ], 409);
        }

        $tipo->tpd_estado = $tipo->tpd_estado == 1 ? 0 : 1;
        $tipo->save();

        return response()->json([
            'message' => $tipo->tpd_estado == 1
                ? 'Tipo de documento activado correctamente'
                : 'Tipo de documento desactivado correctamente',
            'data'    => new TipoDocumentoResource($tipo),
        ], 200);
    }
}
