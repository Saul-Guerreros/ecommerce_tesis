<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Sucursal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SucursalController extends Controller
{
    private function rules(Request $request, ?int $id = null): array
    {
        return [
            'suc_empid' => 'required|integer|exists:empresa,emp_id',
            'suc_ciuid' => 'required|integer|exists:ciudad,ciu_id',
            'suc_nombre' => [
                'required',
                'string',
                'max:50',
                // UNIQUE compuesto (suc_empid, suc_nombre): el nombre solo debe
                // ser único dentro de la misma empresa.
                Rule::unique('sucursal', 'suc_nombre')
                    ->where(fn ($q) => $q->where('suc_empid', $request->suc_empid))
                    ->ignore($id, 'suc_id'),
            ],
            'suc_direccion' => 'nullable|string|max:100',
            'suc_telef'     => 'nullable|string|max:20',
            'suc_cel'       => 'nullable|string|max:20',
            'suc_email'     => 'nullable|email|max:60',
            'suc_estado'    => 'nullable|integer|in:0,1',
        ];
    }

    private function messages(): array
    {
        return [
            'suc_empid.required' => 'Debe seleccionar la empresa.',
            'suc_empid.exists'   => 'La empresa seleccionada no existe.',
            'suc_ciuid.required' => 'Debe seleccionar la ciudad.',
            'suc_ciuid.exists'   => 'La ciudad seleccionada no existe.',
            'suc_nombre.required' => 'El nombre de la sucursal es obligatorio.',
            'suc_nombre.unique'   => 'Esa empresa ya tiene una sucursal con ese nombre.',
            'suc_nombre.max'      => 'El nombre no puede superar los 50 caracteres.',
            'suc_email.email'     => 'El correo electrónico no tiene un formato válido.',
        ];
    }

    // GET /api/admin/sucursales?search=&empresa=
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('sucursal')
            ->join('empresa', 'suc_empid', '=', 'emp_id')
            ->join('ciudad', 'suc_ciuid', '=', 'ciu_id')
            ->select(
                'sucursal.*',
                'empresa.emp_razonsocial',
                'ciudad.ciu_descri'
            );

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

        $sucursales = $query->orderBy('emp_razonsocial')
                            ->orderBy('suc_nombre')
                            ->get();

        // Conteo de depósitos por sucursal, tolerante a que la tabla no exista aún.
        $conteos = Schema::hasTable('deposito')
            ? DB::table('deposito')
                  ->select('dpo_sucid', DB::raw('COUNT(*) AS total'))
                  ->groupBy('dpo_sucid')
                  ->pluck('total', 'dpo_sucid')
            : collect();

        $sucursales->each(function ($suc) use ($conteos) {
            $suc->depositos_count = (int) ($conteos->get($suc->suc_id) ?? 0);
        });

        return response()->json(['data' => $sucursales], 200);
    }

    // GET /api/admin/sucursales/config  -> datos para los <select> del formulario
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

    // GET /api/admin/sucursales/{id}
    public function show(int $id): JsonResponse
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json(['message' => 'Sucursal no encontrada'], 404);
        }

        return response()->json(['data' => $sucursal], 200);
    }

    // POST /api/admin/sucursales
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules($request), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $validator->validated();
        $datos['suc_estado'] = $datos['suc_estado'] ?? 1;

        $sucursal = Sucursal::create($datos);

        return response()->json([
            'message' => 'Sucursal registrada correctamente',
            'data'    => $sucursal,
        ], 201);
    }

    // PUT /api/admin/sucursales/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json(['message' => 'Sucursal no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($request, $id), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $sucursal->update($validator->validated());

        return response()->json([
            'message' => 'Sucursal actualizada correctamente',
            'data'    => $sucursal->fresh(),
        ], 200);
    }

    // DELETE /api/admin/sucursales/{id}
    public function destroy(int $id): JsonResponse
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json(['message' => 'Sucursal no encontrada'], 404);
        }

        if (Schema::hasTable('deposito')) {
            $depositos = DB::table('deposito')->where('dpo_sucid', $id)->count();

            if ($depositos > 0) {
                return response()->json([
                    'message' => "No se puede eliminar: la sucursal tiene {$depositos} depósito(s) asociado(s). Desactívela en lugar de eliminarla.",
                ], 409);
            }
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

        return response()->json([
            'message' => $sucursal->suc_estado == 1
                ? 'Sucursal activada correctamente'
                : 'Sucursal desactivada correctamente',
            'data'    => $sucursal,
        ], 200);
    }
}