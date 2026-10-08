<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cliente\DatoFiscalClienteRequest;
use App\Http\Resources\Cliente\DatoFiscalClienteResource;
use App\Models\Persona;
use App\Models\TipoDocumento;
use App\Support\RucParaguayo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DatoFiscalClienteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(): JsonResponse
    {
        $persona = $this->personaAutenticada();

        $datos = $persona->datosFiscales()
            ->orderByDesc('dfi_principal')
            ->orderByDesc('dfi_id')
            ->get();

        return response()->json([
            'data' => DatoFiscalClienteResource::collection($datos),
        ]);
    }

    /**
     * GET /tienda/perfil/dato-fiscal/verificar-propio?numero=1234567-8
     *
     * Responde si el numero tipeado en el mini-formulario de RUC es el
     * RUC propio del cliente logueado, para que el frontend pueda
     * autocompletar telefono y correo sin volver a pedirlos. Se
     * confirma en dos pasos, de mas a menos certero:
     *
     *  1. Comparar contra cliente.cli_ruc: si el cliente ya cargo su
     *     RUC en su perfil alguna vez, es un match exacto, no una
     *     suposicion.
     *  2. Si no hay cli_ruc guardado pero si hay persona.per_cedula,
     *     calcular el digito verificador esperado para esa cedula
     *     (RucParaguayo) y compararlo contra lo tipeado. En Paraguay el
     *     RUC de una persona fisica es, casi siempre, su cedula mas ese
     *     digito -- ver RucParaguayo para la fuente del algoritmo.
     *     Si da el match, se devuelve ofrecer_guardar_ruc=true para que
     *     el frontend pueda proponerle guardarlo en su perfil y la
     *     proxima vez entre directo por el paso 1.
     *
     * No requiere que el numero pertenezca a un dato_fiscal ya
     * guardado: se calcula en el momento, mientras el cliente todavia
     * esta escribiendo.
     */
    public function verificarPropio(Request $request): JsonResponse
    {
        $numero = trim((string) $request->query('numero', ''));

        if ($numero === '') {
            return response()->json(['es_propio' => false]);
        }

        $persona = $this->personaAutenticada();
        $persona->loadMissing('cliente', 'contactos');

        $esPropio = false;
        $fuente = null;
        $ofrecerGuardarRuc = false;

        $cliRuc = $persona->cliente?->cli_ruc;
        if ($cliRuc && $this->normalizarRuc($numero) === $this->normalizarRuc($cliRuc)) {
            $esPropio = true;
            $fuente = 'perfil';
        } elseif ($persona->per_cedula && RucParaguayo::correspondeACedula($numero, $persona->per_cedula)) {
            $esPropio = true;
            $fuente = 'calculado';
            // Si ya tiene cli_ruc guardado (aunque distinto a este
            // numero), no se le vuelve a ofrecer: ya paso por esto una
            // vez y pudo tener un motivo para no guardarlo.
            $ofrecerGuardarRuc = !$cliRuc;
        }

        if (!$esPropio) {
            return response()->json(['es_propio' => false]);
        }

        return response()->json([
            'es_propio' => true,
            'fuente' => $fuente,
            'telefono' => $this->telefonoDe($persona),
            'correo' => auth('api')->user()->usu_login,
            'ofrecer_guardar_ruc' => $ofrecerGuardarRuc,
        ]);
    }

    private function normalizarRuc(string $ruc): string
    {
        return preg_replace('/\s+/', '', $ruc);
    }

    private function telefonoDe(Persona $persona): ?string
    {
        return $persona->contactos
            ->whereIn('con_tipo', ['CELULAR', 'TELEFONO'])
            ->where('con_estado', 1)
            ->sortBy(fn ($c) => $c->con_tipo === 'CELULAR' ? 0 : 1)
            ->first()?->con_valor;
    }

    public function store(DatoFiscalClienteRequest $request): JsonResponse
    {
        $persona = $this->personaAutenticada();
        $data = $request->validated();
        $data['dfi_tdocid'] = $this->tdoIdRuc();

        $datoFiscal = DB::transaction(function () use ($persona, $data) {
            if (!empty($data['dfi_principal'])) {
                $persona->datosFiscales()->update(['dfi_principal' => 0]);
            } elseif (!$persona->datosFiscales()->exists()) {
                // Primer RUC del cliente: queda predeterminado solo,
                // aunque no haya tildado la casilla.
                $data['dfi_principal'] = true;
            }

            return $persona->datosFiscales()->create($data);
        });

        return response()->json([
            'message' => 'Dato fiscal guardado correctamente.',
            'data' => new DatoFiscalClienteResource($datoFiscal),
        ], 201);
    }

    public function update(DatoFiscalClienteRequest $request, string $id): JsonResponse
    {
        $persona = $this->personaAutenticada();
        $datoFiscal = $persona->datosFiscales()->findOrFail($id);
        $data = $request->validated();

        DB::transaction(function () use ($persona, $datoFiscal, $data) {
            if (!empty($data['dfi_principal'])) {
                $persona->datosFiscales()
                    ->where('dfi_id', '<>', $datoFiscal->dfi_id)
                    ->update(['dfi_principal' => 0]);
            }
            $datoFiscal->update($data);
        });

        return response()->json([
            'message' => 'Dato fiscal actualizado correctamente.',
            'data' => new DatoFiscalClienteResource($datoFiscal->fresh()),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $persona = $this->personaAutenticada();
        $datoFiscal = $persona->datosFiscales()->findOrFail($id);
        $eraPrincipal = (bool) $datoFiscal->dfi_principal;

        $datoFiscal->delete();

        if ($eraPrincipal) {
            $persona->datosFiscales()->orderByDesc('dfi_id')->first()?->update(['dfi_principal' => true]);
        }

        return response()->json(['message' => 'Dato fiscal eliminado correctamente.']);
    }

    private function tdoIdRuc(): int
    {
        return TipoDocumento::where('tpd_codigo', 'RUC')->value('tpd_id');
    }

    private function personaAutenticada(): Persona
    {
        $usuario = auth('api')->user();
        $usuario->loadMissing('rol', 'persona');

        abort_if($usuario->rol?->rol_descri !== 'Cliente', 403, 'Este recurso es exclusivo para clientes.');

        return $usuario->persona;
    }
}