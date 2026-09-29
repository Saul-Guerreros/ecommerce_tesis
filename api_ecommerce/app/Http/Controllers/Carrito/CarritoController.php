<?php

namespace App\Http\Controllers\Carrito;

use App\Http\Controllers\Controller;
use App\Http\Requests\Carrito\CarritoRequest;
use App\Http\Requests\Carrito\SincronizarCarritoRequest;
use App\Http\Resources\Carrito\CarritoResource;
use App\Models\Carrito\Carrito;
use App\Models\Carrito\ConfiguracionCuota;
use App\Models\product\VariacionProducto;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CarritoController extends Controller{
    private const CON=[
        'producto.publicacionWeb.imagenes',
        'producto.publicacionWeb.categoria', // para mostrar la categoría junto al nombre
        'producto.stock',
        'producto.tipoIva', // punto 16: CarritoResource usa esto para desglosar el IVA incluido.
        'variacion.propiedades.atributo',
    ];

    // GET /api/tienda/carrito
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

    // POST /api/tienda/carrito
    public function store(CarritoRequest $request): JsonResponse{
        $usuario = auth('api')->user();
        $datos = $request->validated();

        // Modalidad y cuotas entran en el chequeo de duplicado: el mismo
        // producto al contado y en 6 cuotas son dos filas distintas.
        $duplicado = Carrito::where('car_idusu', $usuario->usu_id)
            ->where('car_idpro', $datos['car_idpro'])
            ->where('car_idvarp', $datos['car_idvarp'] ?? null)
            ->where('car_modpag', $datos['car_modpag'] ?? 'contado')
            ->where('car_numcuo', $datos['car_numcuo'] ?? null)
            ->exists();

        if ($duplicado){
            throw ValidationException::withMessages([
                'car_idpro' => 'Ese producto ya está en tu carrito con esa modalidad. Actualiza la cantidad en vez de agregarlo de nuevo.',
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

        // El precio NUNCA viene del request.
        $precioUnitario = $this->precioUnitarioOFallar($producto, $variacion, (float) $datos['car_cantid']);

        // El recargo y el monto de la cuota TAMPOCO: se recalculan acá.
        $financiacion = $this->resolverFinanciacion(
            $producto,
            $datos['car_modpag'] ?? 'contado',
            $datos['car_numcuo'] ?? null,
            $precioUnitario,
            true
        );

        $carrito = Carrito::create([
            'car_idusu'      => $usuario->usu_id,
            'car_idpro'      => $producto->prd_id,
            'car_idvarp'     => $variacion?->var_proid,
            'car_modpag'     => $financiacion['modalidad'],
            'car_numcuo'     => $financiacion['numero_cuotas'],
            'car_recargopct' => $financiacion['recargo_pct'],
            'car_montocuo'   => $financiacion['monto_cuota'],
            'car_cantid'     => $datos['car_cantid'],
            'car_precio'     => $precioUnitario,
            'car_subtot'     => $financiacion['total_unitario'],
            'car_total'      => $financiacion['total_unitario'] * $datos['car_cantid'],
        ]);

        $carrito->load(self::CON);

        return response()->json([
            'message' => 'Producto agregado al carrito.',
            'data' => new CarritoResource($carrito),
        ], 201);
    }

    // PUT/PATCH /api/tienda/carrito/{carrito} — solo cambia la cantidad
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

        $precioUnitario = $this->precioUnitarioOFallar($producto, $variacion, (float) $datos['car_cantid']);

        // Modo NO estricto: si la opción de cuotas fue dada de baja entre
        // medio, no se rompe el carrito, se cae a contado.
        $financiacion = $this->resolverFinanciacion(
            $producto,
            $carrito->car_modpag,
            $carrito->car_numcuo,
            $precioUnitario,
            false
        );

        $carrito->update([
            'car_cantid'     => $datos['car_cantid'],
            'car_precio'     => $precioUnitario,
            'car_modpag'     => $financiacion['modalidad'],
            'car_numcuo'     => $financiacion['numero_cuotas'],
            'car_recargopct' => $financiacion['recargo_pct'],
            'car_montocuo'   => $financiacion['monto_cuota'],
            'car_subtot'     => $financiacion['total_unitario'],
            'car_total'      => $financiacion['total_unitario'] * $datos['car_cantid'],
        ]);

        $carrito->load(self::CON);

        return response()->json([
            'message' => 'Cantidad actualizada.',
            'data' => new CarritoResource($carrito),
        ]);
    }

    // DELETE /api/tienda/carrito/{carrito}
    public function destroy(Carrito $carrito): JsonResponse{
        $this->verificarPropietario($carrito);

        $carrito->delete();

        return response()->json(['message'=> 'Producto eliminado del carrito.']);
    }

    // DELETE /api/tienda/carrito
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

        $producto->loadMissing('stock');

        return $producto->stockDisponible();
    }

    /**
     * Único lugar donde se resuelve la financiación: store, update,
     * sincronizar y previsualizar lo comparten, así la cuota que el cliente
     * ve en el Home es exactamente la que se guarda.
     *
     * $estricto = true  -> opción inexistente/inactiva corta con 422.
     * $estricto = false -> cae a contado sin romper la operación.
     */
    private function resolverFinanciacion(
        Producto $producto,
        ?string $modalidad,
        $numeroCuotas,
        float $precioUnitario,
        bool $estricto
    ): array {
        $contado = [
            'modalidad'      => 'contado',
            'numero_cuotas'  => null,
            'recargo_pct'    => null,
            'monto_cuota'    => null,
            'total_unitario' => $precioUnitario,
        ];

        if ($modalidad !== 'credito' || ! $numeroCuotas){
            return $contado;
        }

        $config = ConfiguracionCuota::where('cfc_prdid', $producto->prd_id)
            ->where('cfc_cantidad', (int) $numeroCuotas)
            ->where('cfc_estado', true)
            ->first();

        if (! $config){
            if ($estricto){
                throw ValidationException::withMessages([
                    'car_numcuo' => 'Esa cantidad de cuotas ya no está disponible para este producto.',
                ]);
            }

            return $contado;
        }

        return [
            'modalidad'      => 'credito',
            'numero_cuotas'  => (int) $config->cfc_cantidad,
            'recargo_pct'    => (float) $config->cfc_recargo_pct,
            'monto_cuota'    => $config->montoCuota($precioUnitario),
            'total_unitario' => $config->precioFinanciado($precioUnitario),
        ];
    }

    private function precioUnitario(Producto $producto, ?VariacionProducto $variacion, float $cantidad): ?float{
        $precioBase = $producto->precioPara($cantidad);

        if ($precioBase === null){
            return null;
        }

        $adicional= (float)($variacion?->var_preadi ?? 0);

        return $precioBase + $adicional;
    }

    private function precioUnitarioOFallar(Producto $producto, ?VariacionProducto $variacion, float $cantidad): float{
        $precio = $this->precioUnitario($producto, $variacion, $cantidad);

        if ($precio !== null){
            return $precio;
        }

        $minima = $producto->cantidadMinimaVenta();

        throw ValidationException::withMessages([
            'car_cantid' => $producto->soloMayorista()
                ? "Este producto se vende únicamente al por mayor: la cantidad mínima es {$minima}."
                : 'Este producto no tiene un precio de venta configurado.',
        ]);
    }

    // POST /api/tienda/carrito/sincronizar
    public function sincronizar(SincronizarCarritoRequest $request): JsonResponse
    {
        $usuario = auth('api')->user();
        $omitidos = [];

        DB::transaction(function () use ($request, $usuario, &$omitidos) {
            foreach ($request->validated()['items'] as $item) {
                $producto = Producto::find($item['car_idpro']);

                if (! $producto) {
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

                $modalidad    = $item['car_modpag'] ?? 'contado';
                $numeroCuotas = $item['car_numcuo'] ?? null;

                $existente = Carrito::where('car_idusu', $usuario->usu_id)
                    ->where('car_idpro', $producto->prd_id)
                    ->whereNull('car_idvarp')
                    ->where('car_modpag', $modalidad)
                    ->where('car_numcuo', $numeroCuotas)
                    ->first();

                $cantidadDeseada = ($existente ? (float) $existente->car_cantid : 0)
                    + (float) $item['car_cantid'];

                $cantidadFinal = min($cantidadDeseada, $disponible);

                if ($cantidadFinal < $cantidadDeseada) {
                    $omitidos[] = [
                        'car_idpro' => $producto->prd_id,
                        'motivo'    => "Cantidad ajustada a {$cantidadFinal} por stock disponible.",
                    ];
                }

                $precioUnitario = $this->precioUnitario($producto, null, $cantidadFinal);

                if ($precioUnitario === null) {
                    $omitidos[] = [
                        'car_idpro' => $producto->prd_id,
                        'motivo'    => "Cantidad minima no alcanzada: {$producto->cantidadMinimaVenta()} unidades.",
                    ];
                    continue;
                }

                $financiacion = $this->resolverFinanciacion(
                    $producto, $modalidad, $numeroCuotas, $precioUnitario, false
                );

                $valores = [
                    'car_cantid'     => $cantidadFinal,
                    'car_precio'     => $precioUnitario,
                    'car_modpag'     => $financiacion['modalidad'],
                    'car_numcuo'     => $financiacion['numero_cuotas'],
                    'car_recargopct' => $financiacion['recargo_pct'],
                    'car_montocuo'   => $financiacion['monto_cuota'],
                    'car_subtot'     => $financiacion['total_unitario'],
                    'car_total'      => $financiacion['total_unitario'] * $cantidadFinal,
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
            'omitidos' => $omitidos,
        ]);
    }

    // POST /api/tienda/carrito/previsualizar (público, sin auth)
    public function previsualizar(SincronizarCarritoRequest $request): JsonResponse
    {
        $items = collect($request->validated()['items'])
            ->map(function (array $item) {
                $producto = Producto::with(['publicacionWeb.imagenes', 'publicacionWeb.categoria', 'stock', 'tipoIva'])->find($item['car_idpro']);

                if (! $producto) {
                    return null;
                }

                $cantidad = min((float) $item['car_cantid'], $this->stockDisponible($producto, null));

                if ($cantidad <= 0) {
                    return null;
                }

                $precioUnitario = $this->precioUnitario($producto, null, $cantidad);

                if ($precioUnitario === null) {
                    return null;
                }

                $financiacion = $this->resolverFinanciacion(
                    $producto,
                    $item['car_modpag'] ?? 'contado',
                    $item['car_numcuo'] ?? null,
                    $precioUnitario,
                    false
                );

                $carrito = new Carrito([
                    'car_idpro'      => $producto->prd_id,
                    'car_idvarp'     => null,
                    'car_cantid'     => $cantidad,
                    'car_precio'     => $precioUnitario,
                    'car_modpag'     => $financiacion['modalidad'],
                    'car_numcuo'     => $financiacion['numero_cuotas'],
                    'car_recargopct' => $financiacion['recargo_pct'],
                    'car_montocuo'   => $financiacion['monto_cuota'],
                    'car_subtot'     => $financiacion['total_unitario'],
                    'car_total'      => $financiacion['total_unitario'] * $cantidad,
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