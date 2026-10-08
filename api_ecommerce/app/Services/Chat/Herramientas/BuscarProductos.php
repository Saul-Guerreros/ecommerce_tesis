<?php

namespace App\Services\Chat\Herramientas;

use App\Services\Chat\{CatalogoChatAdapter, ContextoChat, ResultadoHerramienta};

class BuscarProductos implements HerramientaChat
{
    private const LIMITE_DEFECTO = 3;
    private const LIMITE_MAXIMO = 5;

    public function __construct(private CatalogoChatAdapter $catalogo)
    {
    }

    public function nombre(): string
    {
        return 'buscar_productos';
    }

    public function declaracion(): array
    {
        return [
            'name' => $this->nombre(),
            'description' => 'Busca productos publicados en la tienda Z Informática. Usar cuando el cliente pide '
                . 'recomendaciones o busca un tipo de producto, una marca, un rango de precio o una especificación. '
                . 'Devuelve precio final en guaraníes, disponibilidad y cuota más baja.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'consulta' => [
                        'type' => 'string',
                        'description' => "Búsqueda en lenguaje natural, ej: 'notebook para diseño menos de 5 millones'",
                    ],
                    'limite' => ['type' => 'integer', 'description' => 'Cantidad de resultados (1 a 5). Por defecto 3.'],
                ],
                'required' => ['consulta'],
            ],
        ];
    }

    public function ejecutar(array $args, ContextoChat $ctx): ResultadoHerramienta
    {
        $consulta = mb_substr(trim((string) ($args['consulta'] ?? '')), 0, 150);

        if ($consulta === '') {
            return ResultadoHerramienta::error('La consulta de búsqueda está vacía.');
        }

        $limite = min(self::LIMITE_MAXIMO, max(1, (int) ($args['limite'] ?? self::LIMITE_DEFECTO)));
        $resultado = $this->catalogo->buscar($consulta, $limite, $ctx->ip);

        if ($resultado['productos'] === []) {
            return new ResultadoHerramienta([
                'total' => 0,
                'nota' => 'Sin resultados. Pedí más detalles al cliente o sugerí una búsqueda más general.',
            ]);
        }

        return new ResultadoHerramienta(
            paraModelo: [
                'total' => $resultado['total'],
                'filtros_aplicados' => $resultado['explicacion'],
                'productos' => array_map([$this->catalogo, 'resumenTarjeta'], $resultado['productos']),
            ],
            tarjetas: $resultado['productos'],
        );
    }
}