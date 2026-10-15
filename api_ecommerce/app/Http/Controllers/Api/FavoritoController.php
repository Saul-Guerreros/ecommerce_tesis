<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorito;
use App\Models\PublicacionWeb;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Lista de favoritos (wishlist) del cliente.
 *
 * - Rutas con sesión (auth:api): ids, index, store, destroy, sincronizar.
 * - Ruta pública: previsualizar (resuelve los favoritos de un INVITADO, que
 *   vive en su localStorage, sin guardar nada en la base).
 *
 * Solo se trabaja con productos PUBLICADOS en la tienda: si un producto se
 * despublica, deja de aparecer en la lista (la fila no se borra, así que
 * vuelve a aparecer si se republica).
 */
class FavoritoController extends Controller
{
    /** Tope por usuario. Debe coincidir con LIMITE_FAVORITOS del frontend. */
    public const LIMITE = 100;

    // GET /api/tienda/favoritos/ids — liviano: solo para pintar los corazones.
    public function ids(): JsonResponse
    {
        return response()->json([
            'data' => $this->idsDelUsuario(auth('api')->user()->usu_id),
        ]);
    }

    // GET /api/tienda/favoritos — productos completos, mismo formato que el Home.
    public function index(VitrinaController $vitrina): JsonResponse
    {
        $ids = $this->idsDelUsuario(auth('api')->user()->usu_id);

        return response()->json([
            'data' => $vitrina->productosPorIds($ids),
        ]);
    }

    // POST /api/tienda/favoritos { prd_id } — idempotente.
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'prd_id' => ['required', 'integer', 'exists:producto,prd_id'],
        ], [
            'prd_id.required' => 'Falta el producto.',
            'prd_id.exists'   => 'El producto no existe.',
        ]);

        $usuarioId  = auth('api')->user()->usu_id;
        $productoId = (int) $datos['prd_id'];

        $publicado = PublicacionWeb::publicadas()->where('pub_prdid', $productoId)->exists();

        if (! $publicado) {
            throw ValidationException::withMessages([
                'prd_id' => 'Este producto ya no está disponible.',
            ]);
        }

        $yaEsta = Favorito::where('fav_idusu', $usuarioId)
            ->where('fav_idpro', $productoId)
            ->exists();

        if (! $yaEsta) {
            if (Favorito::where('fav_idusu', $usuarioId)->count() >= self::LIMITE) {
                throw ValidationException::withMessages([
                    'prd_id' => 'Podés guardar hasta ' . self::LIMITE . ' favoritos. Quitá alguno para agregar otro.',
                ]);
            }

            // insertOrIgnore: si dos pestañas lo agregan a la vez, la restricción
            // UNIQUE descarta el duplicado sin lanzar excepción.
            Favorito::query()->insertOrIgnore([
                'fav_idusu'  => $usuarioId,
                'fav_idpro'  => $productoId,
                'fav_creado' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Producto agregado a favoritos.',
            'data'    => ['prd_id' => $productoId],
        ], $yaEsta ? 200 : 201);
    }

    // DELETE /api/tienda/favoritos/{producto} — idempotente (si no estaba, no falla).
    public function destroy(int $producto): JsonResponse
    {
        Favorito::where('fav_idusu', auth('api')->user()->usu_id)
            ->where('fav_idpro', $producto)
            ->delete();

        return response()->json(['message' => 'Producto quitado de favoritos.']);
    }

    // POST /api/tienda/favoritos/sincronizar { ids: [..] }
    // Fusiona los favoritos que el invitado guardó en su navegador con los de su cuenta.
    public function sincronizar(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => ['present', 'array', 'max:' . self::LIMITE],
            'ids.*' => ['integer', 'distinct'],
        ], [
            'ids.max' => 'Se excedió la cantidad máxima de favoritos a sincronizar.',
        ]);

        $usuarioId = auth('api')->user()->usu_id;
        $recibidos = collect($request->input('ids'))->map(fn ($id) => (int) $id)->unique()->values();

        // Solo productos que existen y están publicados.
        $validos = PublicacionWeb::publicadas()
            ->whereIn('pub_prdid', $recibidos)
            ->pluck('pub_prdid')
            ->map(fn ($id) => (int) $id);

        $yaGuardados = Favorito::where('fav_idusu', $usuarioId)->pluck('fav_idpro')->map(fn ($id) => (int) $id);
        $cupo        = max(0, self::LIMITE - $yaGuardados->count());

        // El invitado guarda "el más nuevo primero". Se insertan del más viejo al más
        // nuevo para que el más nuevo quede con el fav_id más alto (arriba de la lista).
        $nuevos = $recibidos
            ->filter(fn (int $id) => $validos->contains($id) && ! $yaGuardados->contains($id))
            ->take($cupo)
            ->reverse()
            ->values();

        if ($nuevos->isNotEmpty()) {
            $ahora = now();

            Favorito::query()->insertOrIgnore(
                $nuevos->map(fn (int $id) => [
                    'fav_idusu'  => $usuarioId,
                    'fav_idpro'  => $id,
                    'fav_creado' => $ahora,
                ])->all()
            );
        }

        return response()->json([
            'data' => $this->idsDelUsuario($usuarioId),
        ]);
    }

    // POST /api/tienda/favoritos/previsualizar { ids: [..] } — PÚBLICA (invitados).
    public function previsualizar(Request $request, VitrinaController $vitrina): JsonResponse
    {
        $request->validate([
            'ids'   => ['present', 'array', 'max:' . self::LIMITE],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $ids = collect($request->input('ids'))->map(fn ($id) => (int) $id)->all();

        return response()->json([
            'data' => $vitrina->productosPorIds($ids),
        ]);
    }

    /**
     * IDs de producto del usuario, el más reciente primero, solo publicados.
     *
     * @return int[]
     */
    private function idsDelUsuario(int $usuarioId): array
    {
        return Favorito::where('fav_idusu', $usuarioId)
            ->whereHas('producto.publicacionWeb', fn ($q) => $q->publicadas())
            ->orderByDesc('fav_id')
            ->pluck('fav_idpro')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}