<?php

namespace App\Http\Controllers\Admin\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\TimbradoRequest;
use App\Http\Resources\Configuracion\TimbradoResource;
use App\Models\Configuracion\Timbrado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimbradoController extends Controller
{
    /**
     * Hoy no hay ninguna tabla con FK a timbrado.tim_id: compra_cab (Modulo 2)
     * guarda una COPIA del numero de timbrado en el momento de la operacion
     * (com_timbra), no una relacion referencial. Por eso no hay DEPENDENCIAS
     * que consultar via FK; el borrado se bloquea con una regla de negocio
     * distinta (ver destroy()): si el timbrado ya tiene numeracion usada.
     */
    private const DEPENDENCIAS = [];

    // GET /api/admin/timbrados?search=&sucursal=&tipo=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = Timbrado::with(['tipoComprobante', 'sucursal']);

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->whereRaw('LOWER(tim_numero) LIKE ?', [$search]);
        }

        if ($request->filled('sucursal')) {
            $query->where('tim_sucid', $request->sucursal);
        }

        if ($request->filled('tipo')) {
            $query->where('tim_tcoid', $request->tipo);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('tim_estado', $request->estado);
        }

        $timbrados = $query->orderByDesc('tim_fechin')->get();

        return response()->json(['data' => TimbradoResource::collection($timbrados)], 200);
    }

    /**
     * GET /api/admin/timbrados/config
     * Tipos de comprobante y sucursales activas para los <select> del formulario.
     */
    public function config(): JsonResponse
    {
        $tipos = DB::table('tipo_comprobante')
            ->where('tco_estado', 1)
            ->orderBy('tco_descri')
            ->get(['tco_id', 'tco_codigo', 'tco_descri']);

        $sucursales = DB::table('sucursal')
            ->join('empresa', 'suc_empid', '=', 'emp_id')
            ->where('suc_estado', 1)
            ->orderBy('suc_nombre')
            ->get(['suc_id', 'suc_nombre', 'emp_razonsocial']);

        return response()->json([
            'tipos'      => $tipos,
            'sucursales' => $sucursales,
        ], 200);
    }

    /**
     * GET /api/admin/timbrados/select
     * Timbrados USABLES para el combo del futuro modulo de Compra/Venta:
     * activos y dentro de su rango de fechas vigente. No alcanza con
     * tim_estado = 1, hay que cruzar tambien la fecha de hoy.
     */
    public function select(): JsonResponse
    {
        $hoy = now()->toDateString();

        $timbrados = DB::table('timbrado')
            ->join('tipo_comprobante', 'tim_tcoid', '=', 'tco_id')
            ->join('sucursal', 'tim_sucid', '=', 'suc_id')
            ->where('tim_estado', 1)
            ->where('tim_fechin', '<=', $hoy)
            ->where('tim_fechfn', '>=', $hoy)
            ->orderBy('suc_nombre')
            ->orderBy('tco_descri')
            ->get(['tim_id', 'tim_numero', 'tim_nroact', 'tco_descri', 'suc_nombre', 'tim_sucid', 'tim_tcoid']);

        return response()->json(['data' => $timbrados], 200);
    }

    // GET /api/admin/timbrados/{id}
    public function show(int $id): JsonResponse
    {
        $timbrado = Timbrado::with(['tipoComprobante', 'sucursal'])->find($id);

        if (!$timbrado) {
            return response()->json(['message' => 'Timbrado no encontrado'], 404);
        }

        return response()->json(['data' => new TimbradoResource($timbrado)], 200);
    }

    // POST /api/admin/timbrados
    public function store(TimbradoRequest $request): JsonResponse
{
    $datos = $request->validated();

    // tim_empid se deriva de la sucursal seleccionada, no del usuario logueado:
    // Usuario no tiene relación directa con empresa (ver Usuario::sucursales()).
    $datos['tim_empid'] = DB::table('sucursal')
        ->where('suc_id', $datos['tim_sucid'])
        ->value('suc_empid');

    $datos['tim_estado'] = $datos['tim_estado'] ?? 1;
    $datos['tim_nroact'] = $datos['tim_nroini'];

    $timbrado = Timbrado::create($datos);
    $timbrado->load(['tipoComprobante', 'sucursal']);

    return response()->json([
        'message' => 'Timbrado registrado correctamente',
        'data'    => new TimbradoResource($timbrado),
    ], 201);
}

    // PUT /api/admin/timbrados/{id}
    public function update(TimbradoRequest $request, int $id): JsonResponse
    {
        $timbrado = Timbrado::find($id);

        if (!$timbrado) {
            return response()->json(['message' => 'Timbrado no encontrado'], 404);
        }

        // tim_nroact nunca se sobreescribe desde este Request: no esta en
        // $request->validated() porque no forma parte de las reglas.
        $timbrado->update($request->validated());
        $timbrado->load(['tipoComprobante', 'sucursal']);

        return response()->json([
            'message' => 'Timbrado actualizado correctamente',
            'data'    => new TimbradoResource($timbrado),
        ], 200);
    }

    /**
     * DELETE /api/admin/timbrados/{id}
     *
     * Regla de negocio (no de FK): un timbrado que ya emitio numeracion
     * (tim_nroact avanzo respecto de tim_nroini) es un documento legal en
     * uso y no puede borrarse, solo desactivarse.
     */
    public function destroy(int $id): JsonResponse
    {
        $timbrado = Timbrado::find($id);

        if (!$timbrado) {
            return response()->json(['message' => 'Timbrado no encontrado'], 404);
        }

        if ($timbrado->tim_nroact != $timbrado->tim_nroini) {
            return response()->json([
                'message' => 'No se puede eliminar: este timbrado ya tiene numeración utilizada. Desactívelo en lugar de eliminarlo.',
            ], 409);
        }

        $timbrado->delete();

        return response()->json(['message' => 'Timbrado eliminado correctamente'], 200);
    }

    // PATCH /api/admin/timbrados/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $timbrado = Timbrado::find($id);

        if (!$timbrado) {
            return response()->json(['message' => 'Timbrado no encontrado'], 404);
        }

        $timbrado->tim_estado = $timbrado->tim_estado == 1 ? 0 : 1;
        $timbrado->save();
        $timbrado->load(['tipoComprobante', 'sucursal']);

        return response()->json([
            'message' => $timbrado->tim_estado == 1
                ? 'Timbrado activado correctamente'
                : 'Timbrado desactivado correctamente',
            'data'    => new TimbradoResource($timbrado),
        ], 200);
    }
}