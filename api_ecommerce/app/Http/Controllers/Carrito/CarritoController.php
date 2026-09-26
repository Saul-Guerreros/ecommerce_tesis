<?php

namespace App\Http\Controllers\Carrito;

use App\Http\Controllers\Controller;
use App\Http\Requests\Carrito\CarritoRequest;
use App\Http\Resources\Carrito\CarritoResource;
use App\Models\Carrito\Carrito;
use App\Models\Compras\Stock;
use App\Models\product\VariacionProducto;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

use App\Http\Requests\Carrito\SincronizarCarritoRequest;
use Illuminate\Support\Facades\DB;

class CarritoController extends Controller{
    /**
     * Relaciones que CarritoResource necesita, para evitar N+1 al listar.
     */
    private const CON=[
        'producto.publicacionWeb.imagenes',
        'variacion.propiedades.atributo',
    ];

    /**
     * GET /api/tienda/carrito
     */
    public function index(): JsonResponse{
        $usuario = auth('api')->user();

        $carritos = Carrito::where('car_idusu', $usuario->usu_id)
            ->with(self::CON)
            ->orderByDesc('car_id')
            ->get();

        return response()->json([
            'data' => CarritoResource::collection($carritos),
        ]);
    }

    /**
     * POST /api/tienda/carrito
     */
    public function store(CarritoRequest $request): JsonResponse{
        $usuario = auth('api')->user();
        $datos = $request->validated();

        // where('car_idvarp', null) en Eloquent se traduce solo a
        // whereNull() -- no compara "= NULL" (eso nunca matchea en SQL).
        $duplicado = Carrito::where('car_idusu', $usuario->usu_id)
            ->where('car_idpro', $datos['car_idpro'])
            ->where('car_idvarp', $datos['car_idvarp'] ?? null)
            ->exists();

        if ($duplicado){
            throw ValidationException::withMessages([
                'car_idpro' => 'Ese producto ya está en tu carrito. Actualiza la cantidad en vez de agregarlo de nuevo.',
            ]);
        }

        $producto = Producto::findOrFail($datos['car_idpro']);
        $variacion = isset($datos['car_idvarp'])
            ? VariacionProducto::findOrFail($datos['car_idvarp'])
            : null;

        $disponible = $this->stockDisponible($producto, $variacion);

        if ($datos['car_cantid'] > $disponible){
            throw ValidationException::withMessages([
                'car_cantid' => "No hay stock suficiente. Disponible: {$disponible}.",
            ]);
        }

        // El precio NUNCA se toma del request: se recalcula acá a partir
        // del precio publicado (pub_prevta) + el adicional de la variación.
        $precioUnitario= $this->precioUnitario($producto, $variacion);
        $total= $precioUnitario * $datos['car_cantid'];

        $carrito= Carrito::create([
            'car_idusu'  => $usuario->usu_id,
            'car_idpro'  => $producto->prd_id,
            'car_idvarp' => $variacion?->var_proid,
            'car_cantid' => $datos['car_cantid'],
            'car_precio' => $precioUnitario,
            'car_subtot' => $precioUnitario,
            'car_total'  => $total,
        ]);

        $carrito->load(self::CON);

        return response()->json([
            'message' => 'Producto agregado al carrito.',
            'data' => new CarritoResource($carrito),
        ], 201);
    }

    /**
     * PUT/PATCH /api/tienda/carrito/{carrito}
     * Solo cambia la cantidad -- precio y total se recalculan siempre.
     */
    public function update(CarritoRequest $request, Carrito $carrito): JsonResponse{
        $this->verificarPropietario($carrito);

        $datos = $request->validated();
        $producto = $carrito->producto;
        $variacion = $carrito->variacion;

        $disponible = $this->stockDisponible($producto, $variacion);

        if ($datos['car_cantid'] > $disponible){
            throw ValidationException::withMessages([
                'car_cantid' => "No hay stock suficiente. Disponible: {$disponible}.",
            ]);
        }

        $precioUnitario = $this->precioUnitario($producto, $variacion);
        $total = $precioUnitario * $datos['car_cantid'];

        $carrito->update([
            'car_cantid' => $datos['car_cantid'],
            'car_precio' => $precioUnitario,
            'car_subtot' => $precioUnitario,
            'car_total'  => $total,
        ]);

        $carrito->load(self::CON);

        return response()->json([
            'message' => 'Cantidad actualizada.',
            'data' => new CarritoResource($carrito),
        ]);
    }

    /**
     * DELETE /api/tienda/carrito/{carrito}
     */
    public function destroy(Carrito $carrito): JsonResponse{
        $this->verificarPropietario($carrito);

        $carrito->delete(); // soft delete (car_elimin)

        return response()->json(['message'=> 'Producto eliminado del carrito.']);
    }

    /**
     * DELETE /api/tienda/carrito
     */
    public function vaciar(): JsonResponse{
        $usuario= auth('api')->user();

        Carrito::where('car_idusu', $usuario->usu_id)->delete();

        return response()->json(['message'=> 'Carrito vaciado.']);
    }

    // ----- Helpers privados -----
    private function verificarPropietario(Carrito $carrito): void{
        $usuario = auth('api')->user();

        if ((int) $carrito->car_idusu !== (int) $usuario->usu_id){
            abort(403, 'No autorizado.');
        }
    }

    private function stockDisponible(Producto $producto, ?VariacionProducto $variacion): float{
        if ($variacion){
            return (float) $variacion->var_stock;
        }

        return (float) Stock::where('sto_prdid', $producto->prd_id)
            ->selectRaw('COALESCE(SUM(sto_cantid - sto_reserv), 0) as disponible')
            ->value('disponible');
    }

    private function precioUnitario(Producto $producto, ?VariacionProducto $variacion):float{
        $precioBase= (float)($producto->publicacionWeb?->pub_prevta ?? 0);
        $adicional= (float)($variacion?->var_preadi ?? 0);

        return $precioBase + $adicional;
    }

        /**
     * POST /api/tienda/carrito/sincronizar
     *
     * Vuelca el carrito que el invitado armó en localStorage al carrito
     * persistente del usuario recién autenticado. Si el producto ya estaba
     * en el carrito, suma las cantidades (topeadas al stock disponible).
     *
     * Los ítems que ya no se pueden agregar (sin stock, producto dado de
     * baja) no abortan la operación: se omiten y se informan, para que el
     * frontend le avise al usuario sin perderle el resto del carrito.
     */
    public function sincronizar(SincronizarCarritoRequest $request): JsonResponse
    {
        $usuario = auth('api')->user();
        $omitidos = [];

        DB::transaction(function () use ($request, $usuario, &$omitidos) {
            foreach ($request->validated()['items'] as $item) {
                $producto = Producto::find($item['car_idpro']);

                if (!$producto) {
                    continue;
                }

                $disponible = $this->stockDisponible($producto, null);

                if ($disponible <= 0) {
                    $omitidos[] = [
                        'car_idpro' => $producto->prd_id,
                        'motivo'    => 'Sin stock disponible.',
                    ];
                    continue;
                }

                $existente = Carrito::where('car_idusu', $usuario->usu_id)
                    ->where('car_idpro', $producto->prd_id)
                    ->whereNull('car_idvarp')
                    ->first();

                $cantidadDeseada = ($existente ? (float) $existente->car_cantid : 0)
                    + (float) $item['car_cantid'];

                // Se topea en vez de rechazar: el usuario igual se lleva
                // lo que si hay, y el frontend le avisa del ajuste.
                $cantidadFinal = min($cantidadDeseada, $disponible);

                if ($cantidadFinal < $cantidadDeseada) {
                    $omitidos[] = [
                        'car_idpro' => $producto->prd_id,
                        'motivo'    => "Cantidad ajustada a {$cantidadFinal} por stock disponible.",
                    ];
                }

                $precioUnitario = $this->precioUnitario($producto, null);

                $valores = [
                    'car_cantid' => $cantidadFinal,
                    'car_precio' => $precioUnitario,
                    'car_subtot' => $precioUnitario,
                    'car_total'  => $precioUnitario * $cantidadFinal,
                ];

                if ($existente) {
                    $existente->update($valores);
                    continue;
                }

                Carrito::create($valores + [
                    'car_idusu'  => $usuario->usu_id,
                    'car_idpro'  => $producto->prd_id,
                    'car_idvarp' => null,
                ]);
            }
        });

        $carritos = Carrito::where('car_idusu', $usuario->usu_id)
            ->with(self::CON)
            ->orderByDesc('car_id')
            ->get();

        return response()->json([
            'message'  => 'Carrito sincronizado.',
            'data'     => CarritoResource::collection($carritos),
            // Excepcion documentada al contrato: el frontend necesita saber
            // que se omitio o ajusto para avisarle al usuario.
            'omitidos' => $omitidos,
        ]);
    }

    /**
     * POST /api/tienda/carrito/previsualizar  (público, sin auth)
     *
     * Resuelve el carrito que el invitado tiene en localStorage: recibe solo
     * ids + cantidades y devuelve los datos completos (título, imagen, precio
     * vigente) con la MISMA forma que el carrito persistido, sin guardar nada.
     * Así el precio nunca viaja desde el navegador.
     */
    public function previsualizar(SincronizarCarritoRequest $request): JsonResponse
    {
        $items = collect($request->validated()['items'])
            ->map(function (array $item) {
                $producto = Producto::with(['publicacionWeb.imagenes'])->find($item['car_idpro']);

                if (!$producto) {
                    return null;
                }

                $precioUnitario = $this->precioUnitario($producto, null);
                $cantidad = min((float) $item['car_cantid'], $this->stockDisponible($producto, null));

                if ($cantidad <= 0) {
                    return null;
                }

                // Instancia NO persistida: solo para pasarla por el Resource
                // y devolver exactamente el mismo shape que el carrito real.
                $carrito = new Carrito([
                    'car_idpro'  => $producto->prd_id,
                    'car_idvarp' => null,
                    'car_cantid' => $cantidad,
                    'car_precio' => $precioUnitario,
                    'car_subtot' => $precioUnitario,
                    'car_total'  => $precioUnitario * $cantidad,
                ]);

                $carrito->setRelation('producto', $producto);
                $carrito->setRelation('variacion', null);

                return $carrito;
            })
            ->filter()
            ->values();

        return response()->json([
            'data' => CarritoResource::collection($items),
        ]);
    }

}