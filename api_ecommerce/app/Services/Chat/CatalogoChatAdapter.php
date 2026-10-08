<?php

namespace App\Services\Chat;

use App\Http\Controllers\Api\VitrinaController;
use App\Models\PublicacionWeb;
use App\Services\Catalogo\BuscadorTiendaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * DEUDA TÉCNICA: delega en VitrinaController hasta extraer la lógica de
 * precios/presentación a servicios (trabajo futuro). Es el ÚNICO punto del
 * chatbot acoplado al controlador y al formato JSON de la vitrina.
 */
class CatalogoChatAdapter
{
    public function __construct(
        private VitrinaController $vitrina,
        private BuscadorTiendaService $buscador,
    ) {
    }

    /** @return array{productos: array, total: int, explicacion: ?string} */
    public function buscar(string $consulta, int $limite, ?string $ip): array
    {
        // Se usa buscar() del controlador (y no el buscador directo) porque
        // ahí se aplica el filtro de precio que detecta la IA.
        $request = Request::create('/api/tienda/productos/buscar', 'GET',
            ['busqueda' => $consulta, 'por_pagina' => $limite],
            [], [], ['REMOTE_ADDR' => $ip ?? '127.0.0.1']);   // límite por IP del intérprete

        $json = $this->vitrina->buscar($request, $this->buscador)->getData(true);

        return [
            'productos' => $json['data'] ?? [],
            'total' => (int) ($json['meta']['total'] ?? 0),
            'explicacion' => $json['meta']['interpretacion']['explicacion'] ?? null,
        ];
    }

    /** Detalle completo de una publicación PUBLICADA, o null. */
    public function detalle(int $pubId): ?array
    {
        $slug = PublicacionWeb::publicadas()->whereKey($pubId)->value('pub_slug');

        return $slug ? ($this->vitrina->detalle($slug)->getData(true)['data'] ?? null) : null;
    }

    /** Resumen de una tarjeta para el modelo (sin imagen ni campos de UI). */
    public function resumenTarjeta(array $p): array
    {
        return $this->sinNulos([
            'id' => $p['pub_id'],
            'titulo' => $p['titulo'],
            'marca' => $p['marca'],
            'categoria' => $p['categoria'],
            'precio_final' => $p['precio_final'],
            'precio_original' => $p['descuento_pct'] ? $p['precio_original'] : null,
            'descuento_pct' => $p['descuento_pct'],
            'disponible' => $this->disponible($p),
            'solo_mayorista' => $p['solo_mayorista'] ?: null,
            'cuota_desde' => $this->cuotaMasBaja($p['cuotas'] ?? []),
        ]);
    }

    /** Resumen del detalle para el modelo: lo necesario para responder y comparar. */
    public function resumenDetalle(array $d): array
    {
        $p = $d['producto'];

        return $this->sinNulos([
            ...$this->resumenTarjeta($p),
            'stock' => $p['controla_stock'] ? $p['stock'] : null,
            'oferta_hasta' => $p['oferta_fin'] ? substr($p['oferta_fin'], 0, 10) : null,
            'minorista' => $this->resumenTramo($d['compra']['minorista'] ?? null),
            'mayorista' => $this->resumenTramo($d['compra']['mayorista'] ?? null),
            'especificaciones' => collect($d['especificaciones'] ?? [])
                ->mapWithKeys(fn ($fila) => [$fila['atributo'] => $fila['valor']])
                ->all() ?: null,
            'descripcion' => $d['descripcion'] ? Str::limit(strip_tags($d['descripcion']), 300) : null,
            'otros_colores' => collect($d['colores_disponibles'] ?? [])->pluck('titulo')->all() ?: null,
            'retiro_en_sucursales' => $d['sucursales_pickup'] ?: null,
        ]);
    }

    private function resumenTramo(?array $tramo): ?array
    {
        if (! $tramo) {
            return null;
        }

        return $this->sinNulos([
            'precio_unitario' => $tramo['precio_unitario'],
            'cantidad_minima' => $tramo['cantidad_minima'] ?? null,
            'cuotas' => collect($tramo['cuotas'] ?? [])
                ->map(fn ($c) => ['cuotas' => $c['cantidad'], 'monto' => $c['monto_cuota'], 'total' => $c['total']])
                ->all() ?: null,
        ]);
    }

    /** controla_stock = false significa "no se controla", no "sin stock". */
    private function disponible(array $p): bool
    {
        return ! $p['controla_stock'] || $p['stock'] > 0;
    }

    private function cuotaMasBaja(array $cuotas): ?array
    {
        $cuota = collect($cuotas)->sortBy('monto_cuota')->first();

        return $cuota ? ['cuotas' => $cuota['cantidad'], 'monto' => $cuota['monto_cuota']] : null;
    }

    /** Quita los null: cada clave ausente es un token menos. */
    private function sinNulos(array $datos): array
    {
        return array_filter($datos, fn ($valor) => $valor !== null);
    }
}