<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\EmpresaRequest;
use App\Http\Resources\Configuracion\EmpresaResource;
use App\Models\Configuracion\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmpresaController extends Controller
{
    /**
     * Tablas que referencian a empresa. Hoy solo sucursal, pero se deja como
     * arreglo (no como consulta puntual) para que agregar una dependencia
     * futura sea sumar una línea acá, no tocar index()/destroy().
     */
    private const DEPENDENCIAS = [
        'sucursal' => ['columna' => 'suc_empid', 'etiqueta' => 'sucursal(es)'],
    ];

    /**
     * Cuenta cuántos registros de otras tablas apuntan a cada empresa.
     * Devuelve [emp_id => ['sucursal' => 2]]
     */
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

            foreach ($conteos as $empId => $total) {
                $usos[$empId][$tabla] = (int) $total;
            }
        }

        return $usos;
    }

    // GET /api/admin/empresas
    public function index(): JsonResponse
    {
        $empresas = Empresa::orderBy('emp_razonsocial')->get();
        $usos     = $this->contarUsos();

        $empresas->each(function ($empresa) use ($usos) {
            $detalle = $usos[$empresa->emp_id] ?? [];
            $empresa->sucursales_count = $detalle['sucursal'] ?? 0;
        });

        return response()->json(['data' => EmpresaResource::collection($empresas)], 200);
    }

    // GET /api/admin/empresas/{id}
    public function show(int $id): JsonResponse
    {
        $empresa = Empresa::find($id);

        if (!$empresa) {
            return response()->json(['message' => 'Empresa no encontrada'], 404);
        }

        return response()->json(['data' => new EmpresaResource($empresa)], 200);
    }

    // POST /api/admin/empresas
    public function store(EmpresaRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $datos['emp_estado'] = $datos['emp_estado'] ?? 1;

        $empresa = Empresa::create($datos);

        return response()->json([
            'message' => 'Empresa registrada correctamente',
            'data'    => new EmpresaResource($empresa),
        ], 201);
    }

    // PUT /api/admin/empresas/{id}
    public function update(EmpresaRequest $request, int $id): JsonResponse
    {
        $empresa = Empresa::find($id);

        if (!$empresa) {
            return response()->json(['message' => 'Empresa no encontrada'], 404);
        }

        $empresa->update($request->validated());

        return response()->json([
            'message' => 'Empresa actualizada correctamente',
            'data'    => new EmpresaResource($empresa->fresh()),
        ], 200);
    }

    /**
     * DELETE /api/admin/empresas/{id}
     *
     * La FK fk_suc_emp está declarada ON DELETE RESTRICT: si la empresa tiene
     * sucursales, PostgreSQL rechaza el DELETE con una excepción SQL cruda.
     * Se verifica antes para devolver un 409 con un mensaje entendible en vez
     * de un error 500. La base de datos sigue siendo la última línea de
     * defensa; esta comprobación solo mejora el mensaje.
     */
    public function destroy(int $id): JsonResponse
    {
        $empresa = Empresa::find($id);

        if (!$empresa) {
            return response()->json(['message' => 'Empresa no encontrada'], 404);
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
                'message' => 'No se puede eliminar: la empresa está siendo utilizada por '
                             . implode(', ', $detalle) . '. Desactívela en lugar de eliminarla.',
            ], 409);
        }

        $empresa->delete();

        return response()->json(['message' => 'Empresa eliminada correctamente'], 200);
    }

    /**
     * PATCH /api/admin/empresas/{id}/estado
     * Alterna entre activo (1) e inactivo (0). Es la alternativa segura al
     * borrado cuando el registro ya tiene datos que dependen de él.
     */
    public function toggleEstado(int $id): JsonResponse
    {
        $empresa = Empresa::find($id);

        if (!$empresa) {
            return response()->json(['message' => 'Empresa no encontrada'], 404);
        }

        $empresa->emp_estado = $empresa->emp_estado == 1 ? 0 : 1;
        $empresa->save();

        return response()->json([
            'message' => $empresa->emp_estado == 1
                ? 'Empresa activada correctamente'
                : 'Empresa desactivada correctamente',
            'data'    => new EmpresaResource($empresa),
        ], 200);
    }
}