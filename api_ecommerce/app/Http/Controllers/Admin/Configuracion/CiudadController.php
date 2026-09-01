<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Ciudad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CiudadController extends Controller
{
    /**
     * Tablas que referencian a ciudad. Se usan para impedir el borrado de una
     * ciudad en uso y para explicarle al usuario dónde está siendo utilizada.
     */
    private const DEPENDENCIAS = [
        'barrio'    => ['columna' => 'bar_ciuid', 'etiqueta' => 'barrio(s)'],
        'calle'     => ['columna' => 'cal_ciuid', 'etiqueta' => 'calle(s)'],
        'sucursal'  => ['columna' => 'suc_ciuid', 'etiqueta' => 'sucursal(es)'],
        'proveedor' => ['columna' => 'pro_ciuid', 'etiqueta' => 'proveedor(es)'],
    ];

    private function rules(Request $request, ?int $id = null): array
    {
        return [
            'ciu_depid' => 'required|integer|exists:departamento,dep_id',
            'ciu_descri' => [
                'required',
                'string',
                'max:50',
                // UNIQUE compuesto (ciu_depid, ciu_descri): el nombre solo debe
                // ser único dentro del mismo departamento. Existen ciudades
                // homónimas en departamentos distintos.
                Rule::unique('ciudad', 'ciu_descri')
                    ->where(fn ($q) => $q->where('ciu_depid', $request->ciu_depid))
                    ->ignore($id, 'ciu_id'),
            ],
            'ciu_estado' => 'nullable|integer|in:0,1',
        ];
    }

    private function messages(): array
    {
        return [
            'ciu_depid.required'  => 'Debe seleccionar el departamento.',
            'ciu_depid.exists'    => 'El departamento seleccionado no existe.',
            'ciu_descri.required' => 'El nombre de la ciudad es obligatorio.',
            'ciu_descri.unique'   => 'Ese departamento ya tiene una ciudad con ese nombre.',
            'ciu_descri.max'      => 'El nombre no puede superar los 50 caracteres.',
        ];
    }

    /**
     * Cuenta cuántos registros de otras tablas apuntan a cada ciudad.
     * Devuelve [ciu_id => ['barrio' => 3, 'sucursal' => 1]]
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

            foreach ($conteos as $ciuId => $total) {
                $usos[$ciuId][$tabla] = (int) $total;
            }
        }

        return $usos;
    }

    // GET /api/admin/ciudades?search=&departamento=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('ciudad')
            ->join('departamento', 'ciu_depid', '=', 'dep_id')
            ->select('ciudad.*', 'departamento.dep_descri');

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

        $ciudades = $query->orderBy('dep_descri')->orderBy('ciu_descri')->get();
        $usos     = $this->contarUsos();

        $ciudades->each(function ($ciu) use ($usos) {
            $detalle = $usos[$ciu->ciu_id] ?? [];
            $ciu->usos_count  = array_sum($detalle);
            $ciu->usos_detalle = $detalle;
        });

        return response()->json(['data' => $ciudades], 200);
    }

    /**
     * GET /api/admin/ciudades/config
     * Departamentos para el <select> del formulario.
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
     * Lista plana para los combos de OTROS formularios (sucursal, proveedor).
     * Solo ciudades activas: no tiene sentido asignar una ciudad dada de baja.
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
        $ciudad = Ciudad::find($id);

        if (!$ciudad) {
            return response()->json(['message' => 'Ciudad no encontrada'], 404);
        }

        return response()->json(['data' => $ciudad], 200);
    }

    // POST /api/admin/ciudades
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules($request), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $validator->validated();
        $datos['ciu_estado'] = $datos['ciu_estado'] ?? 1;

        $ciudad = Ciudad::create($datos);

        return response()->json([
            'message' => 'Ciudad registrada correctamente',
            'data'    => $ciudad,
        ], 201);
    }

    // PUT /api/admin/ciudades/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $ciudad = Ciudad::find($id);

        if (!$ciudad) {
            return response()->json(['message' => 'Ciudad no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($request, $id), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ciudad->update($validator->validated());

        return response()->json([
            'message' => 'Ciudad actualizada correctamente',
            'data'    => $ciudad->fresh(),
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
                'message' => 'No se puede eliminar: la ciudad está siendo utilizada por '
                             . implode(', ', $detalle) . '. Desactívela en lugar de eliminarla.',
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
            'data'    => $ciudad,
        ], 200);
    }
}