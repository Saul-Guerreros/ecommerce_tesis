<?php

namespace App\Services\Chat\Herramientas;

use App\Services\Chat\{CatalogoChatAdapter, ContextoChat, ResultadoHerramienta};

class VerProducto implements HerramientaChat
{
    public function __construct(private CatalogoChatAdapter $catalogo)
    {
    }

    public function nombre(): string
    {
        return 'ver_producto';
    }

    public function declaracion(): array
    {
        return [
            'name' => $this->nombre(),
            'description' => 'Obtiene ficha técnica, precio final en guaraníes, opciones de cuotas, precio mayorista, '
                . 'stock y sucursales de retiro de UN producto. Usar antes de afirmar cualquier dato de un producto '
                . 'concreto. Para comparar, llamarla una vez por cada producto.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'producto_id' => ['type' => 'integer', 'description' => 'El id devuelto por buscar_productos'],
                ],
                'required' => ['producto_id'],
            ],
        ];
    }

    public function ejecutar(array $args, ContextoChat $ctx): ResultadoHerramienta
    {
        $id = (int) ($args['producto_id'] ?? 0);
        $detalle = $id > 0 ? $this->catalogo->detalle($id) : null;

        if (! $detalle) {
            return ResultadoHerramienta::error("No existe un producto publicado con id {$id}.");
        }

        return new ResultadoHerramienta(
            paraModelo: $this->catalogo->resumenDetalle($detalle),
            tarjetas: [$detalle['producto']],
        );
    }
}