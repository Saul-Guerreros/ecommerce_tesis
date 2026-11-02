<?php

namespace App\Http\Controllers\Admin\Cliente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cliente\StoreClienteRequest;
use App\Http\Resources\Cliente\ClienteResource;
use App\Models\Cliente;
use App\Models\Persona;
use App\Models\TipoCliente;
use App\Models\TipoDocumento;
use App\Services\Cliente\ClienteRapidoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\Cliente\VinculacionPresencialService;
use App\Services\Servicio\NotificadorServicio;

class ClienteController extends Controller
{
    private const TIPOS_CONTACTO  = ['CELULAR','TELEFONO','WHATSAPP','EMAIL'];
    private const ESTADOS_CIVILES = ['SOLTERO','CASADO','DIVORCIADO','VIUDO','UNION_LIBRE'];

       public function __construct(
        private ClienteRapidoService $clientes,
        private VinculacionPresencialService $vinculacion,
        private NotificadorServicio $notificador,
    ) {}

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
                'persona.usuario:usu_id,usu_perid',
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

        $items  = $clientes->items();
        $conMov = Cliente::idsConMovimientos(collect($items)->pluck('cli_id')->all());

        foreach ($items as $c) {
            $c->setAttribute('tiene_movimientos', in_array($c->cli_id, $conMov));
        }

        return response()->json([
            'total' => $clientes->total(),
            'data'  => ClienteResource::collection($items),
        ], 200);
    }

    /** Flujo híbrido: busca la persona por documento antes de crearla */
    public function buscarPersona(Request $request)
    {
        $request->validate([
            'tipo_documento' => ['required','integer'],
            'documento'      => ['required','string'],
        ]);

        $persona = $this->clientes->personaPorDocumento((int) $request->tipo_documento, $request->documento);

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

        // La lógica de alta vive en ClienteRapidoService (compartida con el mostrador de servicio técnico).
        $cliente = $this->clientes->registrar($data);

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

            $this->clientes->sincronizarContactos($cliente->persona, $data['contactos']);
        });

        $cliente = $cliente->fresh()->load('persona.contactos', 'tipoCliente');
        $cliente->setAttribute(
            'tiene_movimientos',
            in_array($cliente->cli_id, Cliente::idsConMovimientos([$cliente->cli_id]))
        );

        return response()->json(['data' => ClienteResource::make($cliente)], 200);
    }

    public function destroy(string $id)
    {
        $cliente = Cliente::with('persona')->findOrFail($id);
        $persona = $cliente->persona;

        // Cualquier movimiento asociado impide eliminar (las FK son RESTRICT): se desactiva en su lugar.
        foreach (Cliente::MOVIMIENTOS as $descripcion => [$tabla, $columna]) {
            if (DB::table($tabla)->where($columna, $cliente->cli_id)->exists()) {
                return response()->json([
                    'message' => "No se puede eliminar el cliente porque tiene {$descripcion} asociadas. Desactívelo en su lugar.",
                ], 409);
            }
        }

        // Cliente registrado desde la tienda: tiene cuenta de acceso (usuario)
        if ($persona->usuario()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el cliente porque tiene una cuenta de usuario en la tienda. Desactívelo en su lugar.',
            ], 409);
        }

        DB::transaction(function () use ($cliente, $persona) {
            $cliente->delete();

            // La persona solo se elimina si no cumple otro rol ni tiene datos propios asociados
            $conservarPersona = $persona->funcionario()->exists()
                || $persona->direcciones()->exists()
                || $persona->datosFiscales()->exists();

            if (!$conservarPersona) {
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

        /**
     * POST /api/admin/clientes/{cliente}/codigo-vinculacion
     * Devuelve el código vigente (lo crea si no existe). Con nuevo=true anula el anterior y emite otro.
     */
    public function codigoVinculacion(Request $request, string $id)
    {
        $cliente = Cliente::with('persona')->findOrFail($id);

        $vinculo = $request->boolean('nuevo')
            ? $this->vinculacion->reemitir($cliente->persona)
            : $this->vinculacion->codigoVigente($cliente->persona);

        if ($vinculo === null) {
            return response()->json([
                'message' => 'El cliente ya tiene cuenta en la tienda o está inactivo.',
            ], 422);
        }

        return response()->json([
            'data' => [
                'codigo'       => $vinculo['codigo'],
                'vence'        => $vinculo['vence']->format('d/m/Y'),
                'enlace'       => $vinculo['enlace'],
                'whatsapp_url' => $this->notificador->enlaceWhatsappVinculacionPersona($cliente->persona, $vinculo),
            ],
        ], 200);
    }
}