<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Deposito;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DepositoController extends Controller
{
    /**
     * Tablas del módulo de compras e inventario que referencian al depósito.
     * Se consultan con Schema::hasTable porque se crean en fases posteriores.
     */
    private const DEPENDENCIAS = [
        'compra_cab'         => ['columna' => 'com_depid', 'etiqueta' => 'compra(s)'],
        'stock'              => ['columna' => 'sto_depid', 'etiqueta' => 'registro(s) de stock'],
        'ajuste_inventario'  => ['columna' => 'aji_depid', 'etiqueta' => 'ajuste(s)'],
    ];

    private function rules(Request $request, ?int $id = null): array
    {
        return [
            'dpo_sucid' => 'required|integer|exists:sucursal,suc_id',
            'dpo_codigo' => [
                'required',
                'string',
                'max:10',
                // UNIQUE compuesto (dpo_sucid, dpo_codigo): el código se repite
                // entre sucursales distintas pero no dentro de la misma.
                Rule::unique('deposito', 'dpo_codigo')
                    ->where(fn ($q) => $q->where('dpo_sucid', $request->dpo_sucid))
                    ->ignore($id, 'dpo_id'),
            ],
            'dpo_nombre' => 'required|string|max:45',
            'dpo_descri' => 'nullable|string|max:45',
            'dpo_princi' => 'nullable|integer|in:0,1',
            'dpo_estado' => 'nullable|integer|in:0,1',
        ];
    }

    private function messages(): array
    {
        return [
            'dpo_sucid.required'  => 'Debe seleccionar la sucursal.',
            'dpo_sucid.exists'    => 'La sucursal seleccionada no existe.',
            'dpo_codigo.required' => 'El código del depósito es obligatorio.',
            'dpo_codigo.unique'   => 'Esa sucursal ya tiene un depósito con ese código.',
            'dpo_codigo.max'      => 'El código no puede superar los 10 caracteres.',
            'dpo_nombre.required' => 'El nombre del depósito es obligatorio.',
            'dpo_nombre.max'      => 'El nombre no puede superar los 45 caracteres.',
        ];
    }

    /**
     * Deja un único depósito principal activo por sucursal.
     *
     * La base tiene un índice único parcial (uq_dpo_princi_suc) que rechazaría
     * el segundo principal con un error SQL. En vez de dejar que falle, se
     * degrada el principal anterior: marcar uno nuevo como principal implica
     * que el anterior deja de serlo.
     */
    private function degradarPrincipalAnterior(int $sucursalId, ?int $excluirId = null): void
    {
        $query = Deposito::where('dpo_sucid', $sucursalId)
                         ->where('dpo_princi', 1);

        if ($excluirId) {
            $query->where('dpo_id', '<>', $excluirId);
        }

        $query->update(['dpo_princi' => 0]);
    }

    // GET /api/admin/depositos?search=&sucursal=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('deposito')
            ->join('sucursal', 'dpo_sucid', '=', 'suc_id')
            ->join('empresa', 'suc_empid', '=', 'emp_id')
            ->select('deposito.*', 'sucursal.suc_nombre', 'empresa.emp_razonsocial');

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(dpo_nombre) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(dpo_codigo) LIKE ?', [$search]);
            });
        }

        if ($request->filled('sucursal')) {
            $query->where('dpo_sucid', $request->sucursal);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('dpo_estado', $request->estado);
        }

        $depositos = $query->orderBy('suc_nombre')
                           ->orderBy('dpo_princi', 'desc')
                           ->orderBy('dpo_nombre')
                           ->get();

        // Marca si el depósito ya tiene movimientos, para habilitar o no el borrado.
        $depositos->each(function ($dpo) {
            $usos = 0;

            foreach (self::DEPENDENCIAS as $tabla => $info) {
                if (Schema::hasTable($tabla)) {
                    $usos += DB::table($tabla)->where($info['columna'], $dpo->dpo_id)->count();
                }
            }

            $dpo->usos_count = $usos;
        });

        return response()->json(['data' => $depositos], 200);
    }

    /**
     * GET /api/admin/depositos/config
     * Sucursales activas para el <select> del formulario, con la empresa
     * en la etiqueta para distinguir homónimas.
     */
    public function config(): JsonResponse
    {
        $sucursales = DB::table('sucursal')
            ->join('empresa', 'suc_empid', '=', 'emp_id')
            ->where('suc_estado', 1)
            ->orderBy('emp_razonsocial')
            ->orderBy('suc_nombre')
            ->get(['suc_id', 'suc_nombre', 'emp_razonsocial']);

        return response()->json(['sucursales' => $sucursales], 200);
    }

    /**
     * GET /api/admin/depositos/select?sucursal=
     * Lista plana para los combos de compra y ajuste (fases 5 y 7).
     */
    public function select(Request $request): JsonResponse
    {
        $query = DB::table('deposito')
            ->join('sucursal', 'dpo_sucid', '=', 'suc_id')
            ->where('dpo_estado', 1)
            ->where('suc_estado', 1);

        if ($request->filled('sucursal')) {
            $query->where('dpo_sucid', $request->sucursal);
        }

        $depositos = $query->orderBy('dpo_princi', 'desc')
                           ->orderBy('dpo_nombre')
                           ->get(['dpo_id', 'dpo_codigo', 'dpo_nombre', 'dpo_princi', 'dpo_sucid', 'suc_nombre']);

        return response()->json(['data' => $depositos], 200);
    }

    // GET /api/admin/depositos/{id}
    public function show(int $id): JsonResponse
    {
        $deposito = Deposito::find($id);

        if (!$deposito) {
            return response()->json(['message' => 'Depósito no encontrado'], 404);
        }

        return response()->json(['data' => $deposito], 200);
    }

    // POST /api/admin/depositos
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules($request), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $validator->validated();
        $datos['dpo_estado'] = $datos['dpo_estado'] ?? 1;
        $datos['dpo_princi'] = $datos['dpo_princi'] ?? 0;

        // El primer depósito de una sucursal se marca principal automáticamente:
        // una sucursal sin depósito principal no puede recibir mercadería.
        $esPrimero = Deposito::where('dpo_sucid', $datos['dpo_sucid'])->count() === 0;

        if ($esPrimero) {
            $datos['dpo_princi'] = 1;
        }

        // La degradación del principal anterior y el alta van juntas: si algo
        // falla en el medio, la sucursal quedaría sin ningún principal.
        $deposito = DB::transaction(function () use ($datos) {
            if ($datos['dpo_princi'] == 1) {
                $this->degradarPrincipalAnterior($datos['dpo_sucid']);
            }

            return Deposito::create($datos);
        });

        return response()->json([
            'message' => 'Depósito registrado correctamente',
            'data'    => $deposito,
        ], 201);
    }

    // PUT /api/admin/depositos/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $deposito = Deposito::find($id);

        if (!$deposito) {
            return response()->json(['message' => 'Depósito no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($request, $id), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $validator->validated();

        // No se permite dejar la sucursal sin depósito principal: si este es el
        // principal actual, solo puede dejar de serlo designando otro.
        if ($deposito->dpo_princi == 1 && ($datos['dpo_princi'] ?? 0) == 0) {
            return response()->json([
                'errors' => [
                    'dpo_princi' => ['Debe designar otro depósito como principal antes de quitarle esta marca.'],
                ],
            ], 422);
        }

        // Un depósito principal no puede desactivarse mientras lo sea.
        if (($datos['dpo_estado'] ?? 1) == 0 && ($datos['dpo_princi'] ?? $deposito->dpo_princi) == 1) {
            return response()->json([
                'errors' => [
                    'dpo_estado' => ['No se puede desactivar el depósito principal. Designe otro primero.'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($deposito, $datos, $id) {
            if (($datos['dpo_princi'] ?? 0) == 1) {
                $this->degradarPrincipalAnterior($datos['dpo_sucid'], $id);
            }

            $deposito->update($datos);
        });

        return response()->json([
            'message' => 'Depósito actualizado correctamente',
            'data'    => $deposito->fresh(),
        ], 200);
    }

    // DELETE /api/admin/depositos/{id}
    public function destroy(int $id): JsonResponse
    {
        $deposito = Deposito::find($id);

        if (!$deposito) {
            return response()->json(['message' => 'Depósito no encontrado'], 404);
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
                'message' => 'No se puede eliminar: el depósito tiene ' . implode(', ', $detalle)
                             . '. Desactívelo en lugar de eliminarlo.',
            ], 409);
        }

        // Si es el principal y quedan otros depósitos, la sucursal se quedaría
        // sin principal. Se exige designar el reemplazo antes de borrar.
        if ($deposito->dpo_princi == 1) {
            $otros = Deposito::where('dpo_sucid', $deposito->dpo_sucid)
                             ->where('dpo_id', '<>', $id)
                             ->count();

            if ($otros > 0) {
                return response()->json([
                    'message' => 'No se puede eliminar el depósito principal. Designe otro como principal antes de eliminarlo.',
                ], 409);
            }
        }

        $deposito->delete();

        return response()->json(['message' => 'Depósito eliminado correctamente'], 200);
    }

    // PATCH /api/admin/depositos/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $deposito = Deposito::find($id);

        if (!$deposito) {
            return response()->json(['message' => 'Depósito no encontrado'], 404);
        }

        if ($deposito->dpo_estado == 1 && $deposito->dpo_princi == 1) {
            return response()->json([
                'message' => 'No se puede desactivar el depósito principal. Designe otro primero.',
            ], 409);
        }

        $deposito->dpo_estado = $deposito->dpo_estado == 1 ? 0 : 1;
        $deposito->save();

        return response()->json([
            'message' => $deposito->dpo_estado == 1
                ? 'Depósito activado correctamente'
                : 'Depósito desactivado correctamente',
            'data'    => $deposito,
        ], 200);
    }

    /**
     * PATCH /api/admin/depositos/{id}/principal
     * Designa este depósito como principal de su sucursal en un solo paso,
     * sin tener que abrir el formulario de edición.
     */
    public function marcarPrincipal(int $id): JsonResponse
    {
        $deposito = Deposito::find($id);

        if (!$deposito) {
            return response()->json(['message' => 'Depósito no encontrado'], 404);
        }

        if ($deposito->dpo_estado == 0) {
            return response()->json([
                'message' => 'Un depósito inactivo no puede ser el principal. Actívelo primero.',
            ], 409);
        }

        DB::transaction(function () use ($deposito, $id) {
            $this->degradarPrincipalAnterior($deposito->dpo_sucid, $id);
            $deposito->dpo_princi = 1;
            $deposito->save();
        });

        return response()->json([
            'message' => 'Depósito marcado como principal',
            'data'    => $deposito->fresh(),
        ], 200);
    }
}