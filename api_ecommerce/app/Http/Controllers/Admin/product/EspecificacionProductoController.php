<?php

namespace App\Http\Controllers\Admin\product;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Product\Atributo;
use App\Models\Product\Propiedad;
use App\Models\Product\EspecificacionProducto;
use App\Models\PublicacionWeb;

/**
 * NOTA: se asume que tu módulo de Atributos/Propiedades (ya construido) usa
 * los modelos App\Models\Product\Atributo (tabla `atributo`, columnas
 * atb_id, atb_nombre, atb_tipo, atb_estado) y App\Models\Product\Propiedad
 * (tabla `propiedad`, columnas prp_id, prp_atbid, prp_nombre, prp_estado).
 * Ajusta los nombres si en tu proyecto son distintos.
 *
 * atb_tipo: 1 = Texto | 2 = Número | 3 = Seleccionable | 4 = Seleccionable múltiple
 *
 * IMPORTANTE: las especificaciones ahora cuelgan de publicacion_web
 * (esp_pubid), no de producto directamente (ver DER, módulo Catálogo).
 * Este controlador sigue recibiendo "producto_id" desde el Angular ya
 * construido (no se tocó esa pantalla) y resuelve internamente la
 * publicación web de ese producto, para no romper el contrato del
 * front-end. Si el producto todavía no fue publicado, se devuelve un
 * error 422 pidiendo publicarlo primero.
 */
class EspecificacionProductoController extends Controller
{
    /**
     * GET /admin/especificaciones?producto_id=1
     */
    public function index(Request $request)
    {
        $request->validate(['producto_id' => 'required|integer']);

        $publicacion = $this->resolverPublicacion($request->producto_id);
        if ($publicacion instanceof \Illuminate\Http\JsonResponse) {
            return $publicacion;
        }

        $especificaciones = EspecificacionProducto::with(['atributo', 'propiedad'])
            ->where('esp_pubid', $publicacion->pub_id)
            ->orderBy('esp_id', 'desc')
            ->get();

        return response()->json([
            'especificaciones' => $especificaciones->map(fn($e) => $this->mapEspecificacion($e)),
        ]);
    }

    /**
     * POST /admin/especificaciones
     * Acepta propiedad_ids como arreglo para soportar atributos "Seleccionable múltiple"
     * (crea una fila por cada propiedad seleccionada). Para tipos Texto/Número/Seleccionable
     * simplemente se envía un arreglo de 1 elemento.
     */
    public function store(Request $request)
    {
        $request->validate([
            'producto_id'   => 'required|integer|exists:producto,prd_id',
            'atributo_id'   => 'required|integer|exists:atributo,atb_id',
            'propiedad_ids' => 'required|array|min:1',
            'propiedad_ids.*' => 'integer|exists:propiedad,prp_id',
            'valor_libre'   => 'nullable|string|max:150',
            'valor_numero'  => 'nullable|numeric',
        ]);

        $publicacion = $this->resolverPublicacion($request->producto_id);
        if ($publicacion instanceof \Illuminate\Http\JsonResponse) {
            return $publicacion;
        }

        $atributo = Atributo::findOrFail($request->atributo_id);

        $validation = $this->validateValueByType($atributo, $request);
        if ($validation) {
            return $validation;
        }

        // Todas las propiedades enviadas deben pertenecer al atributo seleccionado
        $invalid = Propiedad::whereIn('prp_id', $request->propiedad_ids)
            ->where('prp_atbid', '<>', $request->atributo_id)
            ->exists();
        if ($invalid) {
            return response()->json([
                'message' => 403,
                'message_text' => 'UNA O MÁS PROPIEDADES NO PERTENECEN AL ATRIBUTO SELECCIONADO',
            ]);
        }

        // Anti-duplicados: publicación + atributo + propiedad ya existente
        $duplicado = EspecificacionProducto::where('esp_pubid', $publicacion->pub_id)
            ->where('esp_atbid', $request->atributo_id)
            ->whereIn('esp_prpid', $request->propiedad_ids)
            ->exists();
        if ($duplicado) {
            return response()->json([
                'message' => 403,
                'message_text' => 'YA EXISTE UNA ESPECIFICACIÓN CON ESA(S) PROPIEDAD(ES) PARA ESTE ATRIBUTO',
            ]);
        }

        $creadas = [];
        foreach ($request->propiedad_ids as $propiedad_id) {
            $especificacion = EspecificacionProducto::create([
                'esp_pubid' => $publicacion->pub_id,
                'esp_atbid' => $request->atributo_id,
                'esp_prpid' => $propiedad_id,
                'esp_valibr' => $request->valor_libre,
                'esp_valnum' => $request->valor_numero,
            ]);
            $creadas[] = $this->mapEspecificacion($especificacion->load(['atributo', 'propiedad']));
        }

        return response()->json([
            'message' => 200,
            'especificaciones' => $creadas,
        ]);
    }

    public function show(string $id)
    {
        $especificacion = EspecificacionProducto::with(['atributo', 'propiedad'])->findOrFail($id);
        return response()->json(['especificacion' => $this->mapEspecificacion($especificacion)]);
    }

    /**
     * PUT /admin/especificaciones/{id}
     * Edita una sola fila existente (cambia su propiedad y/o su valor libre/numérico).
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'producto_id'  => 'required|integer|exists:producto,prd_id',
            'atributo_id'  => 'required|integer|exists:atributo,atb_id',
            'propiedad_id' => 'required|integer|exists:propiedad,prp_id',
            'valor_libre'  => 'nullable|string|max:150',
            'valor_numero' => 'nullable|numeric',
        ]);

        $publicacion = $this->resolverPublicacion($request->producto_id);
        if ($publicacion instanceof \Illuminate\Http\JsonResponse) {
            return $publicacion;
        }

        $atributo = Atributo::findOrFail($request->atributo_id);

        $validation = $this->validateValueByType($atributo, $request, true);
        if ($validation) {
            return $validation;
        }

        $propiedad = Propiedad::findOrFail($request->propiedad_id);
        if ($propiedad->prp_atbid != $request->atributo_id) {
            return response()->json([
                'message' => 403,
                'message_text' => 'LA PROPIEDAD NO PERTENECE AL ATRIBUTO SELECCIONADO',
            ]);
        }

        $duplicado = EspecificacionProducto::where('esp_pubid', $publicacion->pub_id)
            ->where('esp_atbid', $request->atributo_id)
            ->where('esp_prpid', $request->propiedad_id)
            ->where('esp_id', '<>', $id)
            ->exists();
        if ($duplicado) {
            return response()->json([
                'message' => 403,
                'message_text' => 'YA EXISTE UNA ESPECIFICACIÓN CON ESA PROPIEDAD PARA ESTE ATRIBUTO',
            ]);
        }

        $especificacion = EspecificacionProducto::findOrFail($id);
        $especificacion->update([
            'esp_atbid' => $request->atributo_id,
            'esp_prpid' => $request->propiedad_id,
            'esp_valibr' => $request->valor_libre,
            'esp_valnum' => $request->valor_numero,
        ]);

        return response()->json([
            'message' => 200,
            'especificacion' => $this->mapEspecificacion($especificacion->load(['atributo', 'propiedad'])),
        ]);
    }

    /**
     * DELETE /admin/especificaciones/{id}
     */
    public function destroy(string $id)
    {
        // TODO: validar que la especificación no esté referenciada en pedidos antes de eliminar.
        // No hay soft delete en esta tabla: el borrado es físico.
        $especificacion = EspecificacionProducto::findOrFail($id);
        $especificacion->delete();

        return response()->json(['message' => 200]);
    }
	
	/**
 * POST /admin/especificaciones/duplicar
 * Copia todas las especificaciones de un producto (origen) hacia otro
 * (destino). Para variantes de color: las características técnicas
 * suelen ser idénticas, solo cambia el color/edición.
 */
	public function duplicar(Request $request)
	{
    $request->validate([
        'producto_id_origen' => 'required|integer|exists:producto,prd_id',
        'producto_id_destino' => 'required|integer|different:producto_id_origen|exists:producto,prd_id',
    ]);

    $origen = $this->resolverPublicacion($request->producto_id_origen);
    if ($origen instanceof \Illuminate\Http\JsonResponse) return $origen;

    $destino = $this->resolverPublicacion($request->producto_id_destino);
    if ($destino instanceof \Illuminate\Http\JsonResponse) return $destino;

    $creadas = [];
    foreach (EspecificacionProducto::where('esp_pubid', $origen->pub_id)->get() as $e) {
        $yaExiste = EspecificacionProducto::where('esp_pubid', $destino->pub_id)
            ->where('esp_atbid', $e->esp_atbid)
            ->where('esp_prpid', $e->esp_prpid)
            ->exists();

        if ($yaExiste) continue;

        $nueva = EspecificacionProducto::create([
            'esp_pubid' => $destino->pub_id,
            'esp_atbid' => $e->esp_atbid,
            'esp_prpid' => $e->esp_prpid,
            'esp_valibr' => $e->esp_valibr,
            'esp_valnum' => $e->esp_valnum,
        ]);
        $creadas[] = $this->mapEspecificacion($nueva->load(['atributo', 'propiedad']));
    }

		return response()->json(['message' => 200, 'especificaciones' => $creadas]);
	}

    /**
     * Valida que, según el tipo de atributo, venga el valor libre o numérico correspondiente.
     * La propiedad SIEMPRE es obligatoria (esp_prpid es NOT NULL en la BD), incluso en
     * atributos de tipo Texto/Número: la propiedad actúa como "ancla/categoría" y el valor
     * real capturado por el usuario va en esp_valibr o esp_valnum.
     */
    private function validateValueByType(Atributo $atributo, Request $request, bool $singlePropiedad = false)
    {
        if ($atributo->atb_tipo == 1 && !$request->valor_libre) {
            return response()->json([
                'message' => 403,
                'message_text' => 'EL VALOR DE TEXTO ES OBLIGATORIO PARA ESTE ATRIBUTO',
            ]);
        }

        if ($atributo->atb_tipo == 2 && $request->valor_numero === null) {
            return response()->json([
                'message' => 403,
                'message_text' => 'EL VALOR NUMÉRICO ES OBLIGATORIO PARA ESTE ATRIBUTO',
            ]);
        }

        return null;
    }

    /**
     * Busca la publicación web del producto indicado. Si el producto
     * todavía no tiene ficha web, devuelve un JsonResponse 422 explicando
     * que hay que publicarlo primero (en vez de crear una publicación
     * "vacía" implícita, lo cual escondería el paso al usuario).
     *
     * @return PublicacionWeb|\Illuminate\Http\JsonResponse
     */
    private function resolverPublicacion(int $productoId)
    {
        $publicacion = PublicacionWeb::where('pub_prdid', $productoId)->first();

        if (! $publicacion) {
            return response()->json([
                'message' => 422,
                'message_text' => 'ESTE PRODUCTO TODAVÍA NO TIENE UNA PUBLICACIÓN WEB. PUBLÍCALO PRIMERO PARA PODER AGREGARLE ESPECIFICACIONES.',
            ], 422);
        }

        return $publicacion;
    }

    private function mapEspecificacion(EspecificacionProducto $e): array
    {
        return [
            'esp_id' => $e->esp_id,
            'esp_pubid' => $e->esp_pubid,
            'esp_atbid' => $e->esp_atbid,
            'atributo' => $e->atributo ? [
                'atb_id' => $e->atributo->atb_id,
                'atb_nombre' => $e->atributo->atb_nombre,
                'atb_tipo' => $e->atributo->atb_tipo,
            ] : null,
            'esp_prpid' => $e->esp_prpid,
            'propiedad' => $e->propiedad ? [
                'prp_id' => $e->propiedad->prp_id,
                'prp_nombre' => $e->propiedad->prp_nombre,
            ] : null,
            'esp_valibr' => $e->esp_valibr,
            'esp_valnum' => $e->esp_valnum,
        ];
    }
}
