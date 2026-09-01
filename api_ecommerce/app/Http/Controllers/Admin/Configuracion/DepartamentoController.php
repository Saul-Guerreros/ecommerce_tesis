<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Departamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DepartamentoController extends Controller
{
    private function rules(?int $id = null): array
    {
        return [
            'dep_descri' => [
                'required',
                'string',
                'max:50',
                // UNIQUE simple: no hay dos departamentos con el mismo nombre
                // en todo el país, a diferencia de ciudad que usa clave compuesta.
                Rule::unique('departamento', 'dep_descri')->ignore($id, 'dep_id'),
            ],
            'dep_estado' => 'nullable|integer|in:0,1',
        ];
    }

    private function messages(): array
    {
        return [
            'dep_descri.required' => 'El nombre del departamento es obligatorio.',
            'dep_descri.unique'   => 'Ya existe un departamento con ese nombre.',
            'dep_descri.max'      => 'El nombre no puede superar los 50 caracteres.',
        ];
    }

    // GET /api/admin/departamentos?search=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('departamento');

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->whereRaw('LOWER(dep_descri) LIKE ?', [$search]);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('dep_estado', $request->estado);
        }

        $departamentos = $query->orderBy('dep_descri')->get();

        // Cantidad de ciudades por departamento: se muestra en la tabla y
        // determina si el registro puede eliminarse.
        $conteos = Schema::hasTable('ciudad')
            ? DB::table('ciudad')
                  ->select('ciu_depid', DB::raw('COUNT(*) AS total'))
                  ->groupBy('ciu_depid')
                  ->pluck('total', 'ciu_depid')
            : collect();

        $departamentos->each(function ($dep) use ($conteos) {
            $dep->ciudades_count = (int) ($conteos->get($dep->dep_id) ?? 0);
        });

        return response()->json(['data' => $departamentos], 200);
    }

    /**
     * GET /api/admin/departamentos/select
     * Lista plana de departamentos activos para el <select> de ciudad.
     */
    public function select(): JsonResponse
    {
        $departamentos = DB::table('departamento')
            ->where('dep_estado', 1)
            ->orderBy('dep_descri')
            ->get(['dep_id', 'dep_descri']);

        return response()->json(['data' => $departamentos], 200);
    }

    // GET /api/admin/departamentos/{id}
    public function show(int $id): JsonResponse
    {
        $departamento = Departamento::find($id);

        if (!$departamento) {
            return response()->json(['message' => 'Departamento no encontrado'], 404);
        }

        return response()->json(['data' => $departamento], 200);
    }

    // POST /api/admin/departamentos
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $validator->validated();
        $datos['dep_estado'] = $datos['dep_estado'] ?? 1;

        $departamento = Departamento::create($datos);

        return response()->json([
            'message' => 'Departamento registrado correctamente',
            'data'    => $departamento,
        ], 201);
    }

    // PUT /api/admin/departamentos/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $departamento = Departamento::find($id);

        if (!$departamento) {
            return response()->json(['message' => 'Departamento no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($id), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $departamento->update($validator->validated());

        return response()->json([
            'message' => 'Departamento actualizado correctamente',
            'data'    => $departamento->fresh(),
        ], 200);
    }

    // DELETE /api/admin/departamentos/{id}
    public function destroy(int $id): JsonResponse
    {
        $departamento = Departamento::find($id);

        if (!$departamento) {
            return response()->json(['message' => 'Departamento no encontrado'], 404);
        }

        if (Schema::hasTable('ciudad')) {
            $ciudades = DB::table('ciudad')->where('ciu_depid', $id)->count();

            if ($ciudades > 0) {
                return response()->json([
                    'message' => "No se puede eliminar: el departamento tiene {$ciudades} ciudad(es) asociada(s). Desactívelo en lugar de eliminarlo.",
                ], 409);
            }
        }

        $departamento->delete();

        return response()->json(['message' => 'Departamento eliminado correctamente'], 200);
    }

    // PATCH /api/admin/departamentos/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $departamento = Departamento::find($id);

        if (!$departamento) {
            return response()->json(['message' => 'Departamento no encontrado'], 404);
        }

        $departamento->dep_estado = $departamento->dep_estado == 1 ? 0 : 1;
        $departamento->save();

        return response()->json([
            'message' => $departamento->dep_estado == 1
                ? 'Departamento activado correctamente'
                : 'Departamento desactivado correctamente',
            'data'    => $departamento,
        ], 200);
    }
}