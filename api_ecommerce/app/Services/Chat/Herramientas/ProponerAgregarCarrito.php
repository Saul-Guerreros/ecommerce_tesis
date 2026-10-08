<?php

namespace App\Services\Chat\Herramientas;

use App\Models\PublicacionWeb;
use App\Services\Chat\{ContextoChat, ResultadoHerramienta};

/**
 * NO modifica el carrito: devuelve una acción que el frontend muestra como
 * botón y el cliente confirma (el carrito de invitado vive en el navegador).
 */
class ProponerAgregarCarrito implements HerramientaChat
{
    public function nombre(): string
    {
        return 'proponer_agregar_carrito';
    }

    public function declaracion(): array
    {
        return [
            'name' => $this->nombre(),
            'description' => 'Muestra al cliente un botón para agregar un producto al carrito. Usar solo cuando '
                . 'el cliente expresa intención de comprar Y el producto está identificado sin ambigüedad. '
                . 'Si hay más de un producto posible, NO la uses: primero preguntá cuál quiere.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'producto_id' => ['type' => 'integer'],
                    'cantidad' => ['type' => 'integer', 'description' => 'Por defecto, la compra mínima del producto'],
                ],
                'required' => ['producto_id'],
            ],
        ];
    }

    public function ejecutar(array $args, ContextoChat $ctx): ResultadoHerramienta
    {
        $pub = PublicacionWeb::publicadas()->with('producto.stock')->find((int) ($args['producto_id'] ?? 0));

        if (! $pub) {
            return ResultadoHerramienta::error('Ese producto no está publicado en la tienda.');
        }

        $producto = $pub->producto;
        $minima = max(1, (int) ceil($producto->cantidadMinimaVenta()));
        $cantidad = max($minima, (int) ($args['cantidad'] ?? $minima));

        if ($producto->prd_afestk && $producto->stockDisponible() < $cantidad) {
            return ResultadoHerramienta::error(
                "Stock insuficiente: hay {$producto->stockDisponible()} unidad(es) y se pidieron {$cantidad}.");
        }

        $nota = $cantidad > (int) ($args['cantidad'] ?? $cantidad)
            ? " La cantidad se ajustó a la compra mínima ({$minima})."
            : '';

        return new ResultadoHerramienta(
            paraModelo: [
                'ok' => true,
                'mensaje' => "Se mostró un botón para agregar {$cantidad} unidad(es) de \"{$pub->pub_titulo}\". "
                    . "El cliente debe confirmarlo: no digas que ya se agregó.{$nota}",
            ],
            acciones: [[
                'tipo' => 'agregar_carrito',
                'pub_id' => $pub->pub_id,
                'prd_id' => $producto->prd_id,     // = car_idpro del CarritoRequest
                'cantidad' => $cantidad,
                'titulo' => $pub->pub_titulo,
            ]],
        );
    }
}