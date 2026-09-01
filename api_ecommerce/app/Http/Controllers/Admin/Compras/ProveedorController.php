<?php

namespace App\Http\Controllers\Admin\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\ContactoProveedor;
use App\Models\Compras\Proveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProveedorController extends Controller
{
    /**
     * Tablas que referencian al proveedor. contacto_proveedor NO figura aca:
     * los contactos se borran en cascada junto con el proveedor, no bloquean.
     */
    private const DEPENDENCIAS = [
        'compra_cab' => ['columna' => 'com_proid', 'etiqueta' => 'compra(s)'],
    ];

    private function rules(Request $request, ?int $id = null): array
    {
        return [
            'pro_tprid' => 'required|integer|exists:tipo_proveedor,tpr_id',
            'pro_ciuid' => 'required|integer|exists:ciudad,ciu_id',

            'pro_ruc' => [
                'required', 'string', 'max:15', 'regex:/^[0-9]{1,13}-[0-9]$/',
                Rule::unique('proveedor', 'pro_ruc')->ignore($id, 'pro_id'),
            ],

            'pro_razon'     => 'required|string|max:150',
            'pro_nomfan'    => 'nullable|string|max:80',
            'pro_direccion' => 'nullable|string|max:100',
            'pro_telef'     => 'nullable|string|max:20',
            'pro_email'     => 'nullable|email|max:60',
            'pro_estado'    => 'nullable|integer|in:0,1',

            // Los contactos viajan dentro del mismo formulario. El array
            // puede venir vacio: un proveedor sin contactos es valido.
            'contactos'                 => 'nullable|array',
            'contactos.*.cpr_id'        => 'nullable|integer|exists:contacto_proveedor,cpr_id',
            'contactos.*.cpr_nombre'    => 'required|string|max:60',
            'contactos.*.cpr_apellido'  => 'nullable|string|max:60',
            'contactos.*.cpr_cargo'     => 'nullable|string|max:40',
            'contactos.*.cpr_telef'     => 'nullable|string|max:20',
            'contactos.*.cpr_email'     => 'nullable|email|max:80',
            'contactos.*.cpr_princi'    => 'nullable|integer|in:0,1',
            'contactos.*.cpr_estado'    => 'nullable|integer|in:0,1',
        ];
    }

    private function messages(): array
    {
        return [
            'pro_tprid.required' => 'Debe seleccionar el tipo de proveedor.',
            'pro_tprid.exists'   => 'El tipo de proveedor seleccionado no existe.',
            'pro_ciuid.required' => 'Debe seleccionar la ciudad.',
            'pro_ciuid.exists'   => 'La ciudad seleccionada no existe.',

            'pro_ruc.required' => 'El RUC es obligatorio.',
            'pro_ruc.regex'    => 'El RUC debe tener el formato 80012345-6.',
            'pro_ruc.unique'   => 'Ya existe un proveedor registrado con ese RUC.',

            'pro_razon.required' => 'La razon social es obligatoria.',
            'pro_razon.max'      => 'La razon social no puede superar los 150 caracteres.',
            'pro_email.email'    => 'El correo electronico no tiene un formato valido.',

            'contactos.*.cpr_nombre.required' => 'El nombre del contacto es obligatorio.',
            'contactos.*.cpr_email.email'     => 'El correo del contacto no tiene un formato valido.',
        ];
    }

    /**
     * Valida el digito verificador del RUC paraguayo (modulo 11).
     * Se calcula sobre el numero base; el resultado debe coincidir con el DV
     * que el usuario escribio despues del guion.
     */
    private function digitoVerificadorValido(string $ruc): bool
    {
        [$base, $dv] = explode('-', $ruc);

        $suma = 0;
        $multiplicador = 2;

        // Se recorre de derecha a izquierda multiplicando por 2, 3, 4...
        for ($i = strlen($base) - 1; $i >= 0; $i--) {
            $suma += (int) $base[$i] * $multiplicador;
            $multiplicador++;

            if ($multiplicador > 11) {
                $multiplicador = 2;
            }
        }

        $resto = $suma % 11;
        $calculado = $resto > 1 ? 11 - $resto : 0;

        return $calculado === (int) $dv;
    }

    /**
     * Reglas que no entran en rules() porque dependen del conjunto completo
     * de contactos, no de un campo suelto.
     */
    private function validarContactos(array $contactos): array
    {
        $errores = [];

        foreach ($contactos as $i => $contacto) {
            // El CHECK ck_cpr_medio exige al menos un medio de contacto.
            $sinTelefono = empty($contacto['cpr_telef']);
            $sinEmail    = empty($contacto['cpr_email']);

            if ($sinTelefono && $sinEmail) {
                $errores["contactos.{$i}.cpr_telef"] = [
                    'Indique al menos un telefono o un correo para el contacto.',
                ];
            }
        }

        // El indice uq_cpr_princi_pro solo admite un principal activo.
        $principales = array_filter(
            $contactos,
            fn ($c) => ($c['cpr_princi'] ?? 0) == 1 && ($c['cpr_estado'] ?? 1) == 1
        );

        if (count($principales) > 1) {
            $errores['contactos'] = ['Solo puede haber un contacto principal.'];
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
     * Sincroniza los contactos enviados con los almacenados:
     *  - con cpr_id  -> se actualiza
     *  - sin cpr_id  -> se crea
     *  - ausente     -> se elimina (el usuario lo quito del formulario)
     *
     * El principal anterior se degrada primero: si se guardara el nuevo antes,
     * el indice unico parcial rechazaria el UPDATE por tener dos principales
     * activos a la vez.
     */
    private function sincronizarContactos(Proveedor $proveedor, array $contactos): void
    {
        $idsEnviados = array_filter(array_column($contactos, 'cpr_id'));

        ContactoProveedor::where('cpr_proid', $proveedor->pro_id)
            ->whereNotIn('cpr_id', $idsEnviados ?: [0])
            ->delete();

        // Se limpia el principal actual antes de asignar el nuevo.
        ContactoProveedor::where('cpr_proid', $proveedor->pro_id)
            ->where('cpr_princi', 1)
            ->update(['cpr_princi' => 0]);

        foreach ($contactos as $contacto) {
            $datos = [
                'cpr_proid'    => $proveedor->pro_id,
                'cpr_nombre'   => $contacto['cpr_nombre'],
                'cpr_apellido' => $contacto['cpr_apellido'] ?? null,
                'cpr_cargo'    => $contacto['cpr_cargo'] ?? null,
                'cpr_telef'    => $contacto['cpr_telef'] ?? null,
                'cpr_email'    => $contacto['cpr_email'] ?? null,
                'cpr_princi'   => $contacto['cpr_princi'] ?? 0,
                'cpr_estado'   => $contacto['cpr_estado'] ?? 1,
            ];

            if (!empty($contacto['cpr_id'])) {
                ContactoProveedor::where('cpr_id', $contacto['cpr_id'])->update($datos);
            } else {
                ContactoProveedor::create($datos);
            }
        }
    }

    // GET /api/admin/proveedores?search=&tipo=&ciudad=&estado=
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('proveedor')
            ->join('tipo_proveedor', 'pro_tprid', '=', 'tpr_id')
            ->join('ciudad', 'pro_ciuid', '=', 'ciu_id')
            ->select('proveedor.*', 'tipo_proveedor.tpr_descri', 'ciudad.ciu_descri');

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(pro_razon) LIKE ?', [$search])
                  ->orWhereRaw('LOWER(pro_nomfan) LIKE ?', [$search])
                  ->orWhere('pro_ruc', 'LIKE', $search);
            });
        }

        if ($request->filled('tipo')) {
            $query->where('pro_tprid', $request->tipo);
        }

        if ($request->filled('ciudad')) {
            $query->where('pro_ciuid', $request->ciudad);
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('pro_estado', $request->estado);
        }

        $proveedores = $query->orderBy('pro_razon')->paginate($request->input('per_page', 15));

        // Contactos y usos por fila: el listado muestra el contacto principal
        // sin obligar al usuario a abrir el detalle.
        $ids = collect($proveedores->items())->pluck('pro_id');

        $principales = ContactoProveedor::whereIn('cpr_proid', $ids)
            ->where('cpr_princi', 1)
            ->where('cpr_estado', 1)
            ->get()
            ->keyBy('cpr_proid');

        $conteos = ContactoProveedor::whereIn('cpr_proid', $ids)
            ->select('cpr_proid', DB::raw('COUNT(*) AS total'))
            ->groupBy('cpr_proid')
            ->pluck('total', 'cpr_proid');

        collect($proveedores->items())->each(function ($pro) use ($principales, $conteos) {
            $principal = $principales->get($pro->pro_id);

            $pro->contacto_principal = $principal ? $principal->nombre_completo : null;
            $pro->contacto_telef     = $principal?->cpr_telef;
            $pro->contactos_count    = (int) ($conteos[$pro->pro_id] ?? 0);
            $pro->usos_count         = $this->contarUsos($pro->pro_id)['total'];
        });

        return response()->json($proveedores, 200);
    }

    /**
     * GET /api/admin/proveedores/config
     * Combos del formulario. Las ciudades llegan como lista plana con el
     * departamento en la etiqueta, igual que en sucursal.
     */
    public function config(): JsonResponse
    {
        $tipos = DB::table('tipo_proveedor')
            ->where('tpr_estado', 1)
            ->orderBy('tpr_descri')
            ->get(['tpr_id', 'tpr_descri']);

        $ciudades = DB::table('ciudad')
            ->join('departamento', 'ciu_depid', '=', 'dep_id')
            ->where('ciu_estado', 1)
            ->orderBy('dep_descri')
            ->orderBy('ciu_descri')
            ->get(['ciu_id', 'ciu_descri', 'dep_descri']);

        return response()->json([
            'tipos'    => $tipos,
            'ciudades' => $ciudades,
        ], 200);
    }

    /**
     * GET /api/admin/proveedores/select?search=
     * Combo para la cabecera de compra (fase 5). Solo activos, y con los
     * datos que la factura necesita congelar en el documento.
     */
    public function select(Request $request): JsonResponse
    {
        $query = DB::table('proveedor')->where('pro_estado', 1);

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(pro_razon) LIKE ?', [$search])
                  ->orWhere('pro_ruc', 'LIKE', $search);
            });
        }

        $proveedores = $query->orderBy('pro_razon')
                             ->limit(50)
                             ->get(['pro_id', 'pro_ruc', 'pro_razon', 'pro_nomfan']);

        return response()->json(['data' => $proveedores], 200);
    }

    /**
     * GET /api/admin/proveedores/{id}
     * Devuelve el proveedor con sus contactos: es lo que el formulario carga
     * al abrir en modo edicion.
     */
    public function show(int $id): JsonResponse
    {
        $proveedor = Proveedor::with(['contactos' => fn ($q) => $q->orderBy('cpr_princi', 'desc')->orderBy('cpr_nombre')])
                              ->find($id);

        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        return response()->json(['data' => $proveedor], 200);
    }

    // POST /api/admin/proveedores
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules($request), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!$this->digitoVerificadorValido($request->pro_ruc)) {
            return response()->json([
                'errors' => ['pro_ruc' => ['El digito verificador del RUC no es correcto.']],
            ], 422);
        }

        $contactos = $request->input('contactos', []);
        $errores   = $this->validarContactos($contactos);

        if (!empty($errores)) {
            return response()->json(['errors' => $errores], 422);
        }

        $datos = $validator->validated();
        unset($datos['contactos']);
        $datos['pro_estado'] = $datos['pro_estado'] ?? 1;

        // Cabecera y contactos van juntos: un proveedor guardado a medias,
        // sin la gente con quien se lo contacta, no sirve.
        $proveedor = DB::transaction(function () use ($datos, $contactos) {
            $proveedor = Proveedor::create($datos);

            if (!empty($contactos)) {
                $this->sincronizarContactos($proveedor, $contactos);
            }

            return $proveedor;
        });

        return response()->json([
            'message' => 'Proveedor registrado correctamente',
            'data'    => $proveedor->load('contactos'),
        ], 201);
    }

    // PUT /api/admin/proveedores/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $proveedor = Proveedor::find($id);

        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($request, $id), $this->messages());

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!$this->digitoVerificadorValido($request->pro_ruc)) {
            return response()->json([
                'errors' => ['pro_ruc' => ['El digito verificador del RUC no es correcto.']],
            ], 422);
        }

        $contactos = $request->input('contactos', []);
        $errores   = $this->validarContactos($contactos);

        if (!empty($errores)) {
            return response()->json(['errors' => $errores], 422);
        }

        // El RUC identifica al proveedor en las facturas ya registradas:
        // cambiarlo reescribiria a quien se le compro.
        if ($request->pro_ruc !== $proveedor->pro_ruc) {
            $usos = $this->contarUsos($id);

            if ($usos['total'] > 0) {
                return response()->json([
                    'errors' => [
                        'pro_ruc' => [
                            'No se puede cambiar el RUC: el proveedor tiene '
                            . implode(', ', $usos['detalle']) . ' registradas.',
                        ],
                    ],
                ], 422);
            }
        }

        $datos = $validator->validated();
        unset($datos['contactos']);

        DB::transaction(function () use ($proveedor, $datos, $contactos) {
            $proveedor->update($datos);
            $this->sincronizarContactos($proveedor, $contactos);
        });

        return response()->json([
            'message' => 'Proveedor actualizado correctamente',
            'data'    => $proveedor->fresh()->load('contactos'),
        ], 200);
    }

    // DELETE /api/admin/proveedores/{id}
    public function destroy(int $id): JsonResponse
    {
        $proveedor = Proveedor::find($id);

        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $usos = $this->contarUsos($id);

        if ($usos['total'] > 0) {
            return response()->json([
                'message' => 'No se puede eliminar: el proveedor tiene ' . implode(', ', $usos['detalle'])
                             . '. Desactivelo en lugar de eliminarlo.',
            ], 409);
        }

        // Los contactos se van en cascada por la FK: no hace falta borrarlos.
        $proveedor->delete();

        return response()->json(['message' => 'Proveedor eliminado correctamente'], 200);
    }

    // PATCH /api/admin/proveedores/{id}/estado
    public function toggleEstado(int $id): JsonResponse
    {
        $proveedor = Proveedor::find($id);

        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $proveedor->pro_estado = $proveedor->pro_estado == 1 ? 0 : 1;
        $proveedor->save();

        return response()->json([
            'message' => $proveedor->pro_estado == 1
                ? 'Proveedor activado correctamente'
                : 'Proveedor desactivado correctamente',
            'data'    => $proveedor,
        ], 200);
    }
}