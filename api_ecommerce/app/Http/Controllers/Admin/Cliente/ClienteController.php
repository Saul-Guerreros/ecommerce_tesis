<?php

namespace App\Http\Controllers\Admin\Cliente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cliente\StoreClienteRequest;
use App\Http\Resources\Cliente\ClienteResource;
use App\Models\Cliente;
use App\Models\Persona;
use App\Models\TipoCliente;
use App\Models\TipoDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClienteController extends Controller
{
    private const TIPOS_CONTACTO  = ['CELULAR','TELEFONO','WHATSAPP','EMAIL'];
    private const ESTADOS_CIVILES = ['SOLTERO','CASADO','DIVORCIADO','VIUDO','UNION_LIBRE'];

    /** Catálogos que necesita el formulario */
    public function config()
    {
        return response()->json([
            'tipos_documento' => TipoDocumento::where('tpd_estado', 1)
                                    ->select('tpd_id','tpd_codigo','tpd_descri')
                                    ->orderBy('tpd_descri')->get(),
            'tipos_cliente'   => TipoCliente::where('tpc_estado', 1)
                                    ->select('tpc_id','tpc_descri')
                                    ->orderBy('tpc_descri')->get(),
            'estados_civiles' => self::ESTADOS_CIVILES,
            'tipos_contacto'  => self::TIPOS_CONTACTO,
        ], 200);
    }

    public function index(Request $request)
    {
        $clientes = Cliente::with([
                'persona',
                'persona.contactos:con_id,con_perid,con_tipo,con_valor',
                'tipoCliente:tpc_id,tpc_descri',
            ])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('cli_ruc', 'ilike', "%{$search}%")
                        ->orWhereHas('persona', fn($p) =>
                            $p->where('per_cedula', 'ilike', "%{$search}%")
                              ->orWhere('per_nombr1', 'ilike', "%{$search}%")
                              ->orWhere('per_apell1', 'ilike', "%{$search}%"))
                        ->orWhereHas('persona.contactos', fn($c) =>
                            $c->where('con_valor', 'ilike', "%{$search}%"));
                });
            })
            ->when($request->filled('estado'), fn($q) => $q->where('cli_estado', $request->estado))
            ->orderBy('cli_id', 'desc')
            ->paginate(25);

        return response()->json([
            'total' => $clientes->total(),
            'data'  => ClienteResource::collection($clientes->items()),
        ], 200);
    }

    /** Flujo híbrido: busca la persona por documento antes de crearla */
    public function buscarPersona(Request $request)
    {
        $request->validate([
            'tipo_documento' => ['required','integer'],
            'documento'      => ['required','string'],
        ]);

        $persona = Persona::with(['cliente','contactos:con_id,con_perid,con_tipo,con_valor'])
            ->where('per_tpdid', $request->tipo_documento)
            ->where('per_cedula', $request->documento)
            ->first();

        if (!$persona) {
            return response()->json(['existe' => false], 200);
        }

        return response()->json([
            'existe'     => true,
            'es_cliente' => (bool) $persona->cliente,
            'cliente_id' => $persona->cliente?->cli_id,
            'persona'    => [
                'per_id'     => $persona->per_id,
                'per_tpdid'  => $persona->per_tpdid,
                'per_cedula' => $persona->per_cedula,
                'per_nombr1' => $persona->per_nombr1,
                'per_nombr2' => $persona->per_nombr2,
                'per_apell1' => $persona->per_apell1,
                'per_apell2' => $persona->per_apell2,
                'per_estciv' => $persona->per_estciv,
                'per_fechna' => $persona->per_fechna?->format('Y-m-d'),
            ],
            'contactos'  => $persona->contactos->map(fn($c) => [
                'con_tipo' => $c->con_tipo, 'con_valor' => $c->con_valor,
            ])->values(),
        ], 200);
    }

    public function store(StoreClienteRequest $request)
    {
        $data = $request->validated();

        $cliente = DB::transaction(function () use ($data) {

            $persona = Persona::updateOrCreate(
                ['per_tpdid' => $data['per_tpdid'], 'per_cedula' => $data['per_cedula']],
                [
                    'per_nombr1' => $data['per_nombr1'],
                    'per_nombr2' => $data['per_nombr2'] ?? null,
                    'per_apell1' => $data['per_apell1'],
                    'per_apell2' => $data['per_apell2'] ?? null,
                    'per_estciv' => $data['per_estciv'] ?? null,
                    'per_fechna' => $data['per_fechna'] ?? null,
                    'per_estado' => 1,
                    'per_cread'  => now(),
                ]
            );

            if ($persona->cliente()->exists()) {
                throw ValidationException::withMessages([
                    'per_cedula' => ['Esta persona ya está registrada como cliente.'],
                ]);
            }

            $cliente = Cliente::create([
                'cli_perid'  => $persona->per_id,
                'cli_tpcid'  => $data['cli_tpcid'],
                'cli_ruc'    => $data['cli_ruc'] ?? null,
                'cli_estado' => $data['cli_estado'],
                'cli_creado' => now(),
            ]);

            $this->sincronizarContactos($persona, $data['contactos']);

            return $cliente;
        });

        return response()->json([
            'data' => ClienteResource::make($cliente->load('persona.contactos','tipoCliente')),
        ], 201);
    }

    public function update(StoreClienteRequest $request, string $id)
    {
        $data    = $request->validated();
        $cliente = Cliente::with('persona')->findOrFail($id);

        DB::transaction(function () use ($cliente, $data) {

            $cliente->persona->update([
                'per_tpdid'  => $data['per_tpdid'],
                'per_cedula' => $data['per_cedula'],
                'per_nombr1' => $data['per_nombr1'],
                'per_nombr2' => $data['per_nombr2'] ?? null,
                'per_apell1' => $data['per_apell1'],
                'per_apell2' => $data['per_apell2'] ?? null,
                'per_estciv' => $data['per_estciv'] ?? null,
                'per_fechna' => $data['per_fechna'] ?? null,
            ]);

            $cliente->update([
                'cli_tpcid'  => $data['cli_tpcid'],
                'cli_ruc'    => $data['cli_ruc'] ?? null,
                'cli_estado' => $data['cli_estado'],
            ]);

            $this->sincronizarContactos($cliente->persona, $data['contactos']);
        });

        return response()->json([
            'data' => ClienteResource::make($cliente->fresh()->load('persona.contactos','tipoCliente')),
        ], 200);
    }

    /** Reemplaza el set completo de contactos de la persona */
    private function sincronizarContactos(Persona $persona, array $contactos): void
    {
        $persona->contactos()->delete();

        foreach ($contactos as $c) {
            $persona->contactos()->create([
                'con_tipo'    => $c['con_tipo'],
                'con_valor'   => trim($c['con_valor']),
                'con_estado'  => 1,
                'con_creado'  => now(),
                'con_modific' => now(),
            ]);
        }
    }

    public function destroy(string $id)
    {
        $cliente = Cliente::with('persona')->findOrFail($id);

        // Descomentar cuando exista el modelo Pedido
        // if ($cliente->pedidos()->exists()) {
        //     return response()->json([
        //         'message' => 'No se puede eliminar el cliente porque tiene pedidos asociados. Desactive el registro en su lugar.'
        //     ], 409);
        // }

        DB::transaction(function () use ($cliente) {
            $persona = $cliente->persona;
            $cliente->delete();

            // Si la persona no tiene otro rol, se elimina junto con sus contactos
            $tieneOtroRol = false;
            // $tieneOtroRol = $persona->funcionario()->exists();

            if (!$tieneOtroRol) {
                $persona->contactos()->delete();
                $persona->delete();
            }
        });

        return response()->json(['message' => 'Cliente eliminado correctamente'], 200);
    }

    public function cambiarEstado(string $id)
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->update(['cli_estado' => $cliente->cli_estado == 1 ? 0 : 1]);

        return response()->json([
            'data'    => ['cli_id' => $cliente->cli_id, 'cli_estado' => $cliente->cli_estado],
            'message' => $cliente->cli_estado == 1 ? 'Cliente activado' : 'Cliente desactivado',
        ], 200);
    }
}