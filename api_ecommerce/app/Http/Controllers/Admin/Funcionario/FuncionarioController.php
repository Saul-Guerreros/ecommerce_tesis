<?php

namespace App\Http\Controllers\Admin\Funcionario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funcionario\StoreFuncionarioRequest;
use App\Http\Resources\Funcionario\FuncionarioResource;
use App\Models\Cargo;
use App\Models\Funcionario;
use App\Models\Persona;
use App\Models\TipoDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FuncionarioController extends Controller
{
    private const TIPOS_CONTACTO  = ['CELULAR', 'TELEFONO', 'WHATSAPP', 'EMAIL'];
    private const ESTADOS_CIVILES = ['SOLTERO', 'CASADO', 'DIVORCIADO', 'VIUDO', 'UNION_LIBRE'];

    /** Catálogos que necesita el formulario y los filtros */
    public function config()
    {
        return response()->json([
            'tipos_documento' => TipoDocumento::where('tpd_estado', 1)
                                    ->select('tpd_id', 'tpd_codigo', 'tpd_descri')
                                    ->orderBy('tpd_descri')->get(),
            'cargos'          => Cargo::where('crg_estado', 1)
                                    ->select('crg_id', 'crg_descr')
                                    ->orderBy('crg_descr')->get(),
            'estados_civiles' => self::ESTADOS_CIVILES,
            'tipos_contacto'  => self::TIPOS_CONTACTO,
        ], 200);
    }

    public function index(Request $request)
    {
        $funcionarios = Funcionario::with([
                'persona',
                'persona.contactos:con_id,con_perid,con_tipo,con_valor',
                'cargo:crg_id,crg_descr',
            ])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('persona', fn($p) =>
                            $p->where('per_cedula', 'ilike', "%{$search}%")
                              ->orWhere('per_nombr1', 'ilike', "%{$search}%")
                              ->orWhere('per_apell1', 'ilike', "%{$search}%"))
                        ->orWhereHas('persona.contactos', fn($c) =>
                            $c->where('con_valor', 'ilike', "%{$search}%"))
                        ->orWhereHas('cargo', fn($c) =>
                            $c->where('crg_descr', 'ilike', "%{$search}%"));
                });
            })
            ->when($request->filled('estado'), fn($q) => $q->where('fun_estado', $request->estado))
            ->when($request->filled('cargo_id'), fn($q) => $q->where('fun_crgid', $request->cargo_id))
            ->orderBy('fun_id', 'desc')
            ->paginate(25);

        return response()->json([
            'total' => $funcionarios->total(),
            'data'  => FuncionarioResource::collection($funcionarios->items()),
        ], 200);
    }

    /** Flujo híbrido: busca la persona por documento antes de crearla */
    public function buscarPersona(Request $request)
    {
        $request->validate([
            'tipo_documento' => ['required', 'integer'],
            'documento'      => ['required', 'string'],
        ]);

        $persona = Persona::with(['funcionario', 'contactos:con_id,con_perid,con_tipo,con_valor'])
            ->where('per_tpdid', $request->tipo_documento)
            ->where('per_cedula', $request->documento)
            ->first();

        if (!$persona) {
            return response()->json(['existe' => false], 200);
        }

        return response()->json([
            'existe'         => true,
            'es_funcionario' => (bool) $persona->funcionario,
            'funcionario_id' => $persona->funcionario?->fun_id,
            'persona'        => [
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
            'contactos'      => $persona->contactos->map(fn($c) => [
                'con_tipo' => $c->con_tipo, 'con_valor' => $c->con_valor,
            ])->values(),
        ], 200);
    }

    public function store(StoreFuncionarioRequest $request)
    {
        $data = $request->validated();

        $funcionario = DB::transaction(function () use ($data) {

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
                ]
            );

            // La migración no tiene índice único en fun_perid: se controla aquí.
            if ($persona->funcionario()->exists()) {
                throw ValidationException::withMessages([
                    'per_cedula' => ['Esta persona ya está registrada como funcionario.'],
                ]);
            }

            $funcionario = Funcionario::create([
                'fun_perid'  => $persona->per_id,
                'fun_crgid'  => $data['fun_crgid'],
                'fun_fecing' => $data['fun_fecing'],
                'fun_fecegr' => $data['fun_fecegr'] ?? null,
                'fun_estado' => $data['fun_estado'],
            ]);

            $this->sincronizarContactos($persona, $data['contactos']);

            return $funcionario;
        });

        return response()->json([
            'data' => FuncionarioResource::make($funcionario->load('persona.contactos', 'cargo')),
        ], 201);
    }

    public function update(StoreFuncionarioRequest $request, string $id)
    {
        $data        = $request->validated();
        $funcionario = Funcionario::with('persona')->findOrFail($id);

        DB::transaction(function () use ($funcionario, $data) {

            $funcionario->persona->update([
                'per_tpdid'  => $data['per_tpdid'],
                'per_cedula' => $data['per_cedula'],
                'per_nombr1' => $data['per_nombr1'],
                'per_nombr2' => $data['per_nombr2'] ?? null,
                'per_apell1' => $data['per_apell1'],
                'per_apell2' => $data['per_apell2'] ?? null,
                'per_estciv' => $data['per_estciv'] ?? null,
                'per_fechna' => $data['per_fechna'] ?? null,
            ]);

            $funcionario->update([
                'fun_crgid'  => $data['fun_crgid'],
                'fun_fecing' => $data['fun_fecing'],
                'fun_fecegr' => $data['fun_fecegr'] ?? null,
                'fun_estado' => $data['fun_estado'],
            ]);

            $this->sincronizarContactos($funcionario->persona, $data['contactos']);
        });

        return response()->json([
            'data' => FuncionarioResource::make($funcionario->fresh()->load('persona.contactos', 'cargo')),
        ], 200);
    }

    /** Reemplaza el set completo de contactos de la persona */
    private function sincronizarContactos(Persona $persona, array $contactos): void
    {
        $persona->contactos()->delete();

        foreach ($contactos as $c) {
            $persona->contactos()->create([
                'con_tipo'   => $c['con_tipo'],
                'con_valor'  => trim($c['con_valor']),
                'con_estado' => 1,
            ]);
        }
    }

    public function destroy(string $id)
    {
        $funcionario = Funcionario::with('persona')->findOrFail($id);
        $persona     = $funcionario->persona;

        // Los funcionarios con acceso al panel se gestionan desde Usuarios:
        // borrarlos aquí dejaría una cuenta de acceso sin funcionario.
        if ($persona->usuario()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el funcionario porque tiene una cuenta de usuario en el sistema. Desactívelo en su lugar.',
            ], 409);
        }

        DB::transaction(function () use ($funcionario, $persona) {
            $funcionario->delete();

            // La persona solo se elimina si no cumple otro rol ni tiene datos propios asociados
            $conservarPersona = $persona->cliente()->exists()
                || $persona->direcciones()->exists()
                || $persona->datosFiscales()->exists();

            if (!$conservarPersona) {
                $persona->contactos()->delete();
                $persona->delete();
            }
        });

        return response()->json(['message' => 'Funcionario eliminado correctamente'], 200);
    }

    public function cambiarEstado(string $id)
    {
        $funcionario = Funcionario::findOrFail($id);
        $funcionario->update(['fun_estado' => $funcionario->fun_estado == 1 ? 0 : 1]);

        return response()->json([
            'data'    => ['fun_id' => $funcionario->fun_id, 'fun_estado' => (int) $funcionario->fun_estado],
            'message' => $funcionario->fun_estado == 1 ? 'Funcionario activado' : 'Funcionario desactivado',
        ], 200);
    }
}
