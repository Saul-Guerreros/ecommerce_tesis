<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EmpresaController extends Controller{
    private function rules(?int $id = null): array{
        return[
            'emp_razonsocial'=>'required|string|max:45',
            'emp_ruc'=>[
                'required',
                'string',
                'max:15',
                Rule::unique('empresa', 'emp_ruc')->ignore($id, 'emp_id'),
            ],
            'emp_telef'=>'nullable|string|max:20',
            'emp_email'=>'nullable|email|max:60',
            'emp_actividad'=>'nullable|string|max:50',
            'emp_estado'=>'nullable|integer|in:0,1',
        ];
    }

    private function messages(): array{
        return [
            'emp_razonsocial.required'=> 'La razón social es obligatoria.',
            'emp_razonsocial.max'=> 'La razón social no puede superar los 45 caracteres.',
            'emp_ruc.required'=> 'El RUC es obligatorio.',
            'emp_ruc.unique'=> 'Ya existe una empresa registrada con ese RUC.',
            'emp_ruc.max'=> 'El RUC no puede superar los 15 caracteres.',
            'emp_email.email'=> 'El correo electrónico no tiene un formato válido.',
        ];
    }

    /**
     * GET /api/admin/empresas
     * Devuelve la lista con la cantidad de sucursales asociadas, dato que el
     * listado usa para avisar si el registro se puede eliminar o no.
     */
    public function index(): JsonResponse{
        $empresas = Empresa::orderBy('emp_razonsocial')->get();

        $conteos = DB::table('sucursal')
            ->select('suc_empid', DB::raw('COUNT(*) AS total'))
            ->groupBy('suc_empid')
            ->pluck('total', 'suc_empid');

        $empresas->each(function ($empresa) use ($conteos) {
            $empresa->sucursales_count = (int) ($conteos[$empresa->emp_id] ?? 0);
        });

        return response()->json(['data' => $empresas], 200);
    }

    /**
     * GET /api/admin/empresas/{id}
     */
    public function show(int $id): JsonResponse{
        $empresa = Empresa::find($id);

        if (!$empresa) {
            return response()->json(['message' => 'Empresa no encontrada'], 404);
        }

        return response()->json(['data' => $empresa], 200);
    }

    /**
     * POST /api/admin/empresas
     */
    public function store(Request $request): JsonResponse{
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $validator->validated();
        $datos['emp_estado'] = $datos['emp_estado'] ?? 1;

        $empresa = Empresa::create($datos);

        return response()->json([
            'message' => 'Empresa registrada correctamente',
            'data'    => $empresa,
        ], 201);
    }

    /**
     * PUT /api/admin/empresas/{id}
     */
    public function update(Request $request, int $id): JsonResponse{
        $empresa = Empresa::find($id);

        if (!$empresa){
            return response()->json(['message' => 'Empresa no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($id), $this->messages());

        if ($validator->fails()){
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $empresa->update($validator->validated());

        return response()->json([
            'message' => 'Empresa actualizada correctamente',
            'data'    => $empresa->fresh(),
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
    public function destroy(int $id): JsonResponse{
        $empresa = Empresa::find($id);

        if (!$empresa){
            return response()->json(['message' => 'Empresa no encontrada'], 404);
        }

        $sucursales = DB::table('sucursal')->where('suc_empid', $id)->count();

        if ($sucursales > 0){
            return response()->json([
                'message' => "No se puede eliminar: la empresa tiene {$sucursales} sucursal(es) asociada(s). Desactívela en lugar de eliminarla.",
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
    public function toggleEstado(int $id): JsonResponse{
        $empresa = Empresa::find($id);

        if (!$empresa){
            return response()->json(['message' => 'Empresa no encontrada'], 404);
        }

        $empresa->emp_estado=$empresa->emp_estado == 1 ? 0 : 1;
        $empresa->save();

        return response()->json([
            'message' => $empresa->emp_estado == 1
                ? 'Empresa activada correctamente'
                : 'Empresa desactivada correctamente',
            'data'    => $empresa,
        ], 200);
    }
}
