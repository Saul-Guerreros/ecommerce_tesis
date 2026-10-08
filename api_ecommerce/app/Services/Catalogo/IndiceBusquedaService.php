<?php

namespace App\Services\Catalogo;

use App\Models\Catalogo\IndiceBusqueda;
use App\Models\Producto;
use App\Models\PublicacionWeb;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mantiene el índice de búsqueda (tabla indice_busqueda).
 *
 * Regla: el índice solo contiene publicaciones PUBLICADAS. Si una
 * publicación pasa a Borrador o Archivado, su fila se elimina.
 *
 * Reindexado diferido: los observers (PublicacionWeb, Especificación,
 * Producto, Marca, Categoría) solo MARCAN la publicación como pendiente.
 * Al terminar la petición se reindexa cada una UNA sola vez. Así, guardar
 * una ficha de 7 filas no dispara 7 reindexados, y el cliente no espera:
 * el trabajo corre después de enviar la respuesta.
 */
class IndiceBusquedaService
{
    /** @var array<int, true> pub_id pendientes de reindexar en esta petición */
    private array $pendientes = [];
    private bool $flushRegistrado = false;

    public function __construct(private FichaTecnicaService $fichaTecnica)
    {
    }

    // =====================================================================
    // Reindexado diferido (lo usan los observers)
    // =====================================================================

    public function marcarPendiente(?int $pubId): void
    {
        if (! $pubId) {
            return;
        }

        $this->pendientes[$pubId] = true;

        if (! $this->flushRegistrado) {
            $this->flushRegistrado = true;
            app()->terminating(fn () => $this->procesarPendientes());
        }
    }

    /** @param iterable<int> $pubIds */
    public function marcarVarios(iterable $pubIds): void
    {
        foreach ($pubIds as $pubId) {
            $this->marcarPendiente((int) $pubId);
        }
    }

    public function procesarPendientes(): void
    {
        $ids = array_keys($this->pendientes);
        $this->pendientes = [];

        if (empty($ids)) {
            return;
        }

        foreach ($ids as $pubId) {
            try {
                $this->indexarPorId($pubId);
            } catch (\Throwable $e) {
                // Un error de índice nunca debe romper la operación del
                // usuario: queda registrado y se corrige con busqueda:reindexar.
                Log::error("Índice de búsqueda: no se pudo reindexar la publicación {$pubId}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->refrescarVocabulario();
    }

    /**
     * Recalcula el vocabulario usado por la corrección ortográfica del
     * buscador (vista materializada busqueda_vocabulario).
     */
    public function refrescarVocabulario(): void
    {
        try {
            // CONCURRENTLY: el buscador sigue respondiendo mientras se recalcula.
            DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY busqueda_vocabulario');
        } catch (\Throwable $e) {
            Log::error('Índice de búsqueda: no se pudo refrescar el vocabulario', ['error' => $e->getMessage()]);
        }
    }

    // =====================================================================
    // Indexado
    // =====================================================================

    public function indexarPorId(int $pubId): void
    {
        $publicacion = PublicacionWeb::find($pubId);

        if (! $publicacion) {
            IndiceBusqueda::whereKey($pubId)->delete();
            return;
        }

        $this->indexar($publicacion);
    }

    /**
     * Crea, actualiza o elimina la fila de una publicación. Si el producto
     * es BASE, reindexa también sus variantes: heredan su ficha técnica.
     */
    public function indexar(PublicacionWeb $publicacion, bool $conVariantes = true): void
    {
        $publicacion->loadMissing(['producto.marca', 'producto.padre', 'categoria.padre']);

        if (! $publicacion->estaPublicada() || ! $publicacion->producto) {
            IndiceBusqueda::whereKey($publicacion->pub_id)->delete();
        } else {
            IndiceBusqueda::updateOrCreate(
                ['ibu_pubid' => $publicacion->pub_id],
                $this->armarFila($publicacion)
            );
        }

        if ($conVariantes) {
            $this->indexarVariantesDe($publicacion->producto);
        }
    }

    /** Reconstruye el índice completo. Devuelve cuántas filas quedaron. */
    public function reindexarTodo(?callable $alAvanzar = null): int
    {
        // Filas huérfanas o de publicaciones que ya no están publicadas.
        IndiceBusqueda::whereNotIn('ibu_pubid', PublicacionWeb::publicadas()->select('pub_id'))->delete();

        PublicacionWeb::publicadas()
            ->with(['producto.marca', 'producto.padre', 'categoria.padre'])
            ->orderBy('pub_id')
            ->chunk(100, function ($publicaciones) use ($alAvanzar) {
                foreach ($publicaciones as $publicacion) {
                    // Sin cascada: el recorrido completo ya pasa por cada variante.
                    $this->indexar($publicacion, conVariantes: false);
                    $alAvanzar && $alAvanzar($publicacion);
                }
            });

        $this->refrescarVocabulario();

        return IndiceBusqueda::count();
    }

    // =====================================================================
    // Armado de la fila
    // =====================================================================

    private function armarFila(PublicacionWeb $publicacion): array
    {
        $producto = $publicacion->producto;
        $categoria = $publicacion->categoria;

        $ficha = $this->fichaTecnica->fichaParaMostrar($publicacion);
        $valores = $this->fichaTecnica->valoresEfectivos($producto, $publicacion);

        return [
            'ibu_prdid'       => $producto->prd_id,
            'ibu_catid'       => $categoria?->cat_id,
            'ibu_catpadr'     => $categoria?->cat_idpadr,
            'ibu_marid'       => $producto->prd_marid,
            'ibu_titulo'      => $publicacion->pub_titulo,
            'ibu_marca'       => $producto->marca?->mar_descri,
            // Subcategoría + categoría principal: "Notebooks Informática".
            'ibu_categoria'   => trim(($categoria?->cat_nombre ?? '') . ' ' . ($categoria?->padre?->cat_nombre ?? '')) ?: null,
            'ibu_ficha_texto' => collect($ficha)->map(fn ($f) => "{$f['atributo']}: {$f['valor']}")->implode(' | ') ?: null,
            'ibu_descripcion' => $this->textoPlano($publicacion->pub_descwb),
            // Vacía se guarda como objeto {} (no como arreglo []) para que
            // las consultas ibu_ficha->'12' funcionen igual en todos los casos.
            'ibu_ficha'       => $this->fichaEstructurada($valores) ?: new \stdClass(),
            'ibu_actualizado' => now(),
        ];
    }

    /**
     * Ficha para filtros (claves = atb_id, estables aunque se renombre el
     * atributo): num = número, txt = texto, prp = ids de propiedades.
     */
    private function fichaEstructurada($valores): array
    {
        return $valores->mapWithKeys(function ($v, $atbId) {
            $dato = [];
            if ($v['valor_numero'] !== null) {
                $dato['num'] = (float) $v['valor_numero'];
            }
            if ($v['valor_texto'] !== null && $v['valor_texto'] !== '') {
                $dato['txt'] = $v['valor_texto'];
            }
            if (! empty($v['propiedad_ids'])) {
                $dato['prp'] = $v['propiedad_ids'];
            }

            return [(string) $atbId => $dato];
        })->filter()->all();
    }

    private function indexarVariantesDe(?Producto $producto): void
    {
        if (! $producto || $producto->prd_padre_id) {
            return; // solo un producto base tiene variantes
        }

        PublicacionWeb::whereIn('pub_prdid', $producto->variantes()->select('prd_id'))
            ->get()
            ->each(fn (PublicacionWeb $variante) => $this->indexar($variante, conVariantes: false));
    }

    /** La descripción puede venir con HTML de un editor: se indexa solo el texto. */
    private function textoPlano(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $texto = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html))));

        return $texto === '' ? null : $texto;
    }
}
