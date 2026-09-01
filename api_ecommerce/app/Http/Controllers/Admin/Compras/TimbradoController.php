<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\Timbrado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class TimbradoController extends Controller
{
    /**
     * Tablas que referencian al timbrado propio de la empresa. Se llenan en
     * las fases de venta; en compras el timbrado del proveedor va congelado
     * como texto dentro de compra_cab, no como FK.
     */
    private const DEPENDENCIAS = [
        'venta' => ['columna' => 'ven_timid', 'etiqueta' => 'venta(s)'],
    ];

    private function rules(Request $request, ?int $id = null): array
    {
        return [
            'tim_empid'    => 'required|integer|exists:empresa,emp_id',
            'tim_sucid'    => 'required|integer|exists:sucursal,suc_id',
            'tim_tcoid'    => 'required|integer|exists:tipo_comprobante,tco_id',
            'tim_numero'   => 'required|string|size:8|regex:/^[0-9]{8}$/',
            'tim_establec' => 'required|string|size:3|regex:/^[0-9]{3}$/',
            'tim_puntexp'  => 'required|string|size:3|regex:/^[0-9]{3}$/',
            'tim_fechin'   => 'required|date',
            'tim_fechfn'   => 'required|date|after_or_equal:tim_fechin',
            'tim_nroini'   => 'required|integer|min:1',
            'tim_nrofin'   => 'required|integer|min:1|gte:tim_nroini',
            'tim_observacion' => 'nullable|string|max:150',
            'tim_estado'   => 'nullable|integer|in:0,1',
        ];
    }

    private function messages(): array
    {
        return [
            'tim_empid.required'    => 'Debe seleccionar la empresa.',
            'tim_empid.exists'      => 'La empresa seleccionada no existe.',
            'tim_sucid.required'    => 'Debe seleccionar la sucursal.',
            'tim_sucid.exists'      => 'La sucursal seleccionada no existe.',
            'tim_tcoid.required'    => 'Debe seleccionar el tipo de comprobante.',
            'tim_tcoid.exists'      => 'El tipo de comprobante seleccionado no existe.',
            'tim_numero.required'   => 'El numero de timbrado es obligatorio.',
            'tim_numero.size'       => 'El numero de timbrado debe tener 8 digitos.',
            'tim_numero.regex'      => 'El numero de timbrado debe ser numerico de 8 digitos.',
            'tim_establec.required' => 'El establecimiento es obligatorio.',
            'tim_establec.regex'    => 'El establecimiento debe ser numerico de 3 digitos (ej: 001).',
            'tim_puntexp.required'  => 'El punto de expedicion es obligatorio.',
            'tim_puntexp.regex'     => 'El punto de expedicion debe ser numerico de 3 digitos (ej: 001).',
            'tim_fechin.required'   => 'La fecha de inicio de vigencia es obligatoria.',
            'tim_fechfn.required'   => 'La fecha de fin de vigencia es obligatoria.',
            'tim_fechfn.after_or_equal' => 'El fin de vigencia no puede ser anterior al inicio.',
            'tim_nroini.required'   => 'El numero inicial es obligatorio.',
            'tim_nroini.min'        => 'El numero inicial debe ser mayor a cero.',
            'tim_nrofin.required'   => 'El numero final es obligatorio.',
            'tim_nrofin.gte'        => 'El numero final debe ser mayor o igual al inicial.',
        ];
    }

    /**
     * Valida reglas que dependen de varias tablas y no entran en rules().
     * Devuelve un arreglo de errores con el mismo formato que el validator.
     */
    private function validarCoherencia(Request $request, ?Timbrado $actual = null): array
    {
        $errores = [];

        // La sucursal debe pertenecer a la empresa seleccionada: un talonario
        // de otra empresa emitiria documentos con el RUC equivocado.
        $sucursal = DB::table('sucursal')->where('suc_id', $request->tim_sucid)->first();

        if ($sucursal && (int) $sucursal->suc_empid !== (int) $request->tim_empid) {
            $errores['tim_sucid'] = ['La sucursal seleccionada no pertenece a esa empresa.'];
        }

        // No se puede solapar la vigencia de dos talonarios del mismo punto de
        // expedicion y tipo de comprobante: el sistema no sabria cual numerar.
        $solapado = Timbrado::where('tim_sucid', $request->tim_sucid)
            ->where('tim_tcoid', $request->tim_tcoid)
            ->where('tim_establec', str_pad((string) $request->tim_establec, 3, '0', STR_PAD_LEFT))
            ->where('tim_puntexp', str_pad((string) $request->tim_puntexp, 3, '0', STR_PAD_LEFT))
            ->where('tim_estado', 1)
            ->when($actual, fn ($q) => $q->where('tim_id', '<>', $actual->tim_id))
            ->where('tim_fechin', '<=', $request->tim_fechfn)
            ->where('tim_fechfn', '>=', $request->tim_fechin)
            ->exists();

        if ($solapado) {
            $errores['tim_fechin'] = [
                'Ya existe un timbrado activo para ese punto de expedicion con vigencia solapada.',
            ];
        }

        return $errores;
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

    /**
     * Agrega los datos derivados que el listado necesita mostrar y que no
     * conviene recalcular en la plantilla: disponibles, usos y vigencia.
     */
    private function formatear($timbrado)
    {
        $hoy = now()->toDateString();

        $timbrado->disponibles = $timbrado->tim_nrofin - $timbrado->tim_nroact;
        $timbrado->usos_count  = $this->contarUsos($timbrado->tim_id)['total'];

        $timbrado->vigente = $timbrado->tim_estado == 1
            && $timbrado->tim_fechin <= $hoy
            && $timbrado->tim_fechfn >= $hoy
            && $timbrado->disponibles > 0;

        // Estado legible: lo que el usuario necesita ver de un vistazo.
        if ($timbrado->tim_estado == 0) {
            $timbrado->situacion = 'Inactivo';
        } elseif ($timbrado->tim_fechfn < $hoy) {
            $timbrado->situacion = 'Vencido';
        } elseif ($timbrado->tim_fechin > $hoy) {
            $timbrado->situacion = 'Pendiente';
        } elseif ($timbrado->disponibles <= 0) {
            $timbrado->situacion = 'Agotado';
        } else {
            $timbrado->situacion = 'Vigente';
        }

        return $timbrado;
    }

    // GET /api/admin/timbrados?search=&sucursal=&comprobante=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('timbrado')
            ->join('sucursal', 'tim_sucid', '=', 'suc_id')
            ->join('empresa', 'tim_empid', '=', 'emp_id')
            ->join('tipo_comprobante', 'tim_tcoid', '=', 'tco_id')
            ->select(
                'timbrado.*',
                'sucursal.suc_nombre',
                'empresa.emp_razonsocial',
                'tipo_comprobante.tco_descri',
                'tipo_comprobante.tco_codigo'
            );

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('tim_numero', 'LIKE', $search)
                  ->orWhereRaw('LOWER(tco_descri) LIKE ?', [$search]);
            });
        }

        if ($request->filled('sucursal')) {
            $query->where('tim_sucid', $request->sucursal);
        }

        if ($request->filled('comprobante')) {
            $query->where('tim_tcoid', $request->comprobante);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('tim_estado', $request->estado);
        }

        $timbrados = $query->orderBy('tim_estado', 'desc')
                           ->orderBy('tim_fechfn', 'desc')
                           ->get();

        $timbrados->each(fn ($t) => $this->formatear($t));

        return response()->json(['data' => $timbrados], 200);
    }

    /**
     * GET /api/admin/timbrados/config
     * Combos del formulario: empresas, sucursales y tipos de comprobante.
     * Las sucursales traen suc_empid para filtrarlas en el cliente segun la
     * empresa elegida, sin ida y vuelta al servidor.
     */
    public function config(): JsonResponse
    {
        $empresas = DB::table('empresa')
            ->where('emp_estado', 1)
            ->orderBy('emp_razonsocial')
            ->get(['emp_id', 'emp_razonsocial', 'emp_ruc']);

        $sucursales = DB::table('sucursal')
            ->where('suc_estado', 1)
            ->orderBy('suc_nombre')
            ->get(['suc_id', 'suc_nombre', 'suc_empid']);

        // Solo los comprobantes que requieren timbrado tienen sentido aca.
        $comprobantes = DB::table('tipo_comprobante')
            ->where('tco_estado', 1)
            ->where('tco_reqtim', 1)
            ->orderBy('tco_codigo')
            ->get(['tco_id', 'tco_codigo', 'tco_descri']);

        return response()->json([
            'empresas'     => $empresas,
            'sucursales'   => $sucursales,
            'comprobantes' => $comprobantes,
        ], 200);
    }

    /**
     * GET /api/admin/timbrados/select?sucursal=&comprobante=
     * Talonarios vigentes para los formularios de emision (fases de venta).
     */
    public function select(Request $request): JsonResponse
    {
        $hoy = now()->toDateString();

        $query = DB::table('timbrado')
            ->join('tipo_comprobante', 'tim_tcoid', '=', 'tco_id')
            ->where('tim_estado', 1)
            ->whereDate('tim_fechin', '<=', $hoy)
            ->whereDate('tim_fechfn', '>=', $hoy)
            ->whereColumn('tim_nroact', '<', 'tim_nrofin');

        if ($request->filled('sucursal')) {
            $query->where('tim_sucid', $request->sucursal);
        }

        if ($request->filled('comprobante')) {
            $query->where('tim_tcoid', $request->comprobante);
        }

        $timbrados = $query->orderBy('tim_fechfn')
            ->get([
                'tim_id', 'tim_numero', 'tim_establec', 'tim_puntexp',
                'tim_nroact', 'tim_nrofin', 'tim_fechfn', 'tim_tcoid', 'tim_sucid',
                'tco_descri',
            ]);

        return response()->json(['data' => $timbrados], 200);
    }

    // GET /api/admin/timbrados/{id}
    public function show(int $id): JsonResponse
    {
        $timbrado = Timbrado::find($id);

        if (!$timbrado) {
            return response()->json(['message' => 'Timbrado no encontrado'], 404);
        }

        return response()->json(['data' => $timbrado], 200);
    }

    // POST /api/admin/timbrados
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules($request), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $errores = $this->validarCoherencia($request);

        if (!empty($errores)) {
            return response()->json(['errors' => $errores], 422);
        }

        $datos = $validator->validated();
        $datos['tim_estado'] = $datos['tim_estado'] ?? 1;

        // Todavia no se emitio ningun documento: el correlativo arranca un
        // numero antes del inicio del rango.
        $datos['tim_nroact'] = $datos['tim_nroini'] - 1;

        $timbrado = Timbrado::create($datos);

        return response()->json([
            'message' => 'Timbrado registrado correctamente',
            'data'    => $timbrado,
        ], 201);
    }

    // PUT /api/admin/timbrados/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $timbrado = Timbrado::find($id);

        if (!$timbrado) {
            return response()->json(['message' => 'Timbrado no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($request, $id), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $errores = $this->validarCoherencia($request, $timbrado);

        if (!empty($errores)) {
            return response()->json(['errors' => $errores], 422);
        }

        $datos = $validator->validated();

        // Un talonario que ya emitio documentos no puede cambiar de identidad
        // ni de rango: eso reescribiria la numeracion de lo ya facturado.
        $emitidos = $timbrado->tim_nroact - ($timbrado->tim_nroini - 1);

        if ($emitidos > 0) {
            $inmutables = ['tim_numero', 'tim_establec', 'tim_puntexp', 'tim_tcoid', 'tim_empid', 'tim_sucid'];

            foreach ($inmutables as $campo) {
                if ((string) $datos[$campo] !== (string) $timbrado->$campo) {
                    return response()->json([
                        'errors' => [
                            $campo => ["No se puede modificar: el timbrado ya emitio {$emitidos} documento(s)."],
                        ],
                    ], 422);
                }
            }

            // El rango puede ampliarse hacia arriba, nunca recortarse por
            // debajo de lo ya emitido.
            if ($datos['tim_nroini'] != $timbrado->tim_nroini) {
                return response()->json([
                    'errors' => ['tim_nroini' => ['No se puede cambiar el numero inicial de un timbrado en uso.']],
                ], 422);
            }

            if ($datos['tim_nrofin'] < $timbrado->tim_nroact) {
                return response()->json([
                    'errors' => ['tim_nrofin' => ["El numero final no puede ser menor al ultimo emitido ({$timbrado->tim_nroact})."]],
                ], 422);
            }
        } else {
            // Sin documentos emitidos el correlativo se realinea al rango.
            $datos['tim_nroact'] = $datos['tim_nroini'] - 1;
        }

        $timbrado->update($datos);

        return response()->json([
            'message' => 'Timbrado actualizado correctamente',
            'data'    => $timbrado->fresh(),
        ], 200);
    }

    // DELETE /api/admin/timbrados/{id}
    public function destroy(int $id): JsonResponse
    {
        $timbrado = Timbrado::find($id);

        if (!$timbrado) {
            return response()->json(['message' => 'Timbrado no encontrado'], 404);
        }

        $usos = $this->contarUsos($id);

        if ($usos['total'] > 0) {
            return response()->json([
                'message' => 'No se puede eliminar: el timbrado tiene ' . implode(', ', $usos['detalle'])
                             . '. Desactivelo en lugar de eliminarlo.',
            ], 409);
        }

        // Aunque no haya documentos en la tabla de ventas, el correlativo
        // avanzado indica numeracion consumida: se conserva como evidencia.
        if ($timbrado->tim_nroact >= $timbrado->tim_nroini) {
            return response()->json([
                'message' => 'No se puede eliminar: el timbrado ya tiene numeracion utilizada.',
            ], 409);
        }

        $timbrado->delete();

        return response()->json(['message' => 'Timbrado eliminado correctamente'], 200);
    }

    /**
     * PATCH /api/admin/timbrados/{id}/estado
     * Activar respeta el indice unico parcial uq_tim_activo_punto: solo puede
     * haber un talonario activo por sucursal, comprobante y punto de
     * expedicion, asi que se verifica antes de intentar el UPDATE.
     */
    public function toggleEstado(int $id): JsonResponse
    {
        $timbrado = Timbrado::find($id);

        if (!$timbrado) {
            return response()->json(['message' => 'Timbrado no encontrado'], 404);
        }

        if ($timbrado->tim_estado == 0) {
            $ocupado = Timbrado::where('tim_sucid', $timbrado->tim_sucid)
                ->where('tim_tcoid', $timbrado->tim_tcoid)
                ->where('tim_establec', $timbrado->tim_establec)
                ->where('tim_puntexp', $timbrado->tim_puntexp)
                ->where('tim_estado', 1)
                ->where('tim_id', '<>', $id)
                ->first();

            if ($ocupado) {
                return response()->json([
                    'message' => "No se puede activar: el timbrado {$ocupado->tim_numero} ya esta activo "
                                 . 'para ese punto de expedicion. Desactivelo primero.',
                ], 409);
            }
        }

        $timbrado->tim_estado = $timbrado->tim_estado == 1 ? 0 : 1;
        $timbrado->save();

        return response()->json([
            'message' => $timbrado->tim_estado == 1
                ? 'Timbrado activado correctamente'
                : 'Timbrado desactivado correctamente',
            'data'    => $timbrado,
        ], 200);
    }
}
