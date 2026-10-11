<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\product\Atributo;
use App\Models\product\Propiedad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AtributoController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->search;

        // with('propiedades'): evita N+1 en transform()
        $atributos = Atributo::with('propiedades')
            ->where('atb_nombre', 'ilike', '%' . $search . '%')
            ->orderBy('atb_id', 'desc')
            ->paginate(25);

        return response()->json([
            'total'     => $atributos->total(),
            'atributos' => $atributos->map(fn ($atributo) => $this->transform($atributo)),
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'atb_nombre' => 'required|string|max:50',
            'atb_tipo'   => 'required|integer|in:1,2,3,4',
            'atb_unidad' => 'nullable|string|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $nombre = trim($request->atb_nombre);

        // withTrashed(): incluye los eliminados lógicamente, igual que el UNIQUE de PostgreSQL
        $existente = Atributo::withTrashed()
            ->whereRaw('LOWER(atb_nombre) = ?', [mb_strtolower($nombre)])
            ->first();

        if ($existente && !$existente->trashed()) {
            return response()->json([
                'errors' => ['atb_nombre' => ['Ya existe un atributo con ese nombre.']],
            ], 422);
        }

        $datos = [
            'atb_nombre' => $nombre,
            'atb_tipo'   => $request->atb_tipo,
            'atb_unidad' => $this->unidadSegunTipo($request),
            'atb_estado' => $request->atb_estado ?? 1,
        ];

        if ($existente) {
            // Estaba eliminado: se restaura con los datos nuevos
            $existente->restore();
            $existente->update($datos);

            return response()->json([
                'message'  => 'Atributo registrado correctamente (se restauró uno eliminado con el mismo nombre)',
                'atributo' => $this->transform($existente->fresh('propiedades')),
            ], 201);
        }

        $atributo = Atributo::create($datos);

        return response()->json([
            'message'  => 'Atributo registrado correctamente',
            'atributo' => $this->transform($atributo),
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'atb_nombre' => 'required|string|max:50',
            'atb_tipo'   => 'required|integer|in:1,2,3,4',
            'atb_unidad' => 'nullable|string|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $atributo = Atributo::find($id);
        if (!$atributo) {
            return response()->json(['message' => 'Atributo no encontrado'], 404);
        }

        $existe = Atributo::where('atb_id', '<>', $id)
            ->where('atb_nombre', $request->atb_nombre)
            ->exists();
        if ($existe) {
            return response()->json([
                'errors' => ['atb_nombre' => ['Ya existe un atributo con ese nombre.']],
            ], 422);
        }

        $atributo->update([
            'atb_nombre' => $request->atb_nombre,
            'atb_tipo'   => $request->atb_tipo,
            'atb_unidad' => $this->unidadSegunTipo($request),
            'atb_estado' => $request->atb_estado ?? $atributo->atb_estado,
        ]);
		
		$duplicado = Atributo::withTrashed()
            ->where('atb_id', '<>', $id)
            ->whereRaw('LOWER(atb_nombre) = ?', [mb_strtolower(trim($request->atb_nombre))])
            ->first();

        if ($duplicado) {
            $msg = $duplicado->trashed()
                ? 'Ese nombre pertenece a un atributo eliminado. Créelo de nuevo para restaurarlo.'
                : 'Ya existe un atributo con ese nombre.';

            return response()->json(['errors' => ['atb_nombre' => [$msg]]], 422);
        }

        return response()->json([
            'message'  => 'Atributo actualizado correctamente',
            'atributo' => $this->transform($atributo),
        ], 200);
    }

    public function destroy(string $id)
    {
        $atributo = Atributo::find($id);
        if (!$atributo) {
            return response()->json(['message' => 'Atributo no encontrado'], 404);
        }

        // Atributo usa SoftDeletes, así que la FK de categoria_atributo no
        // lo frena: sin este control desaparecería en silencio de las
        // Plantillas de Ficha Técnica. Primero hay que quitarlo de ellas.
        $plantillas = DB::table('categoria_atributo')
            ->join('categoria', 'cat_id', '=', 'cta_catid')
            ->where('cta_atbid', $atributo->atb_id)
            ->pluck('cat_nombre');

        if ($plantillas->isNotEmpty()) {
            return response()->json([
                'message' => 'No se puede eliminar: el atributo se usa en la ficha técnica de '
                    . $plantillas->implode(', ') . '. Quítelo primero de esas plantillas.',
            ], 409);
        }

        $atributo->delete();

        return response()->json(['message' => 'Atributo eliminado correctamente'], 200);
    }

    public function storePropiedad(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'prp_atbid'  => 'required|integer|exists:atributo,atb_id',
            'prp_nombre' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existe = Propiedad::where('prp_atbid', $request->prp_atbid)
            ->where('prp_nombre', $request->prp_nombre)
            ->exists();
        if ($existe) {
            return response()->json([
                'errors' => ['prp_nombre' => ['Esa propiedad ya existe para este atributo.']],
            ], 422);
        }

        $propiedad = Propiedad::create([
            'prp_atbid'  => $request->prp_atbid,
            'prp_nombre' => $request->prp_nombre,
            'prp_codigo' => $request->prp_codigo ?? '',
        ]);

        return response()->json([
            'message'   => 'Propiedad agregada correctamente',
            'propiedad' => [
                'prp_id'     => $propiedad->prp_id,
                'prp_atbid'  => $propiedad->prp_atbid,
                'prp_nombre' => $propiedad->prp_nombre,
                'prp_codigo' => $propiedad->prp_codigo,
            ],
        ], 201);
    }

    public function destroyPropiedad(string $id)
    {
        $propiedad = Propiedad::find($id);
        if (!$propiedad) {
            return response()->json(['message' => 'Propiedad no encontrada'], 404);
        }

        $propiedad->delete();

        return response()->json(['message' => 'Propiedad eliminada correctamente'], 200);
    }

    /**
     * La unidad solo tiene sentido en atributos de tipo Número (2):
     * "16" + "GB". En los demás tipos se descarta aunque llegue cargada.
     */
    private function unidadSegunTipo(Request $request): ?string
    {
        if ((int) $request->atb_tipo !== 2) {
            return null;
        }

        $unidad = trim((string) $request->atb_unidad);

        return $unidad === '' ? null : $unidad;
    }

    private function transform(Atributo $atributo): array
    {
        return [
            'atb_id'      => $atributo->atb_id,
            'atb_nombre'  => $atributo->atb_nombre,
            'atb_tipo'    => $atributo->atb_tipo,
            'atb_unidad'  => $atributo->atb_unidad,
            'atb_estado'  => $atributo->atb_estado,
            'atb_creado'  => $atributo->atb_creado,
            'propiedades' => $atributo->propiedades->map(fn ($p) => [
                'prp_id'     => $p->prp_id,
                'prp_atbid'  => $p->prp_atbid,
                'prp_nombre' => $p->prp_nombre,
                'prp_codigo' => $p->prp_codigo,
            ]),
        ];
    }
}