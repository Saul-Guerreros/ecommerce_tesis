<?php

namespace App\Observers;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Product\EspecificacionProducto;
use App\Models\product\categorie;
use App\Models\Producto;
use App\Models\PublicacionWeb;
use App\Services\Catalogo\IndiceBusquedaService;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer (patrón Observer de Eloquent) que mantiene actualizado el
 * índice de búsqueda. Un único observer para todos los modelos que
 * alimentan el índice: cada evento se traduce a "qué publicaciones
 * quedaron desactualizadas" y se marcan como pendientes. El reindexado
 * real ocurre una sola vez al final de la petición (IndiceBusquedaService).
 *
 * Nota: solo detecta cambios hechos con Eloquent. Un DB::table()->update()
 * sobre estas tablas no dispara eventos; en ese caso, usar
 * php artisan busqueda:reindexar.
 */
class IndiceBusquedaObserver
{
    public function __construct(private IndiceBusquedaService $indice)
    {
    }

    public function saved(Model $modelo): void
    {
        $this->marcar($modelo);
    }

    public function deleted(Model $modelo): void
    {
        $this->marcar($modelo);
    }

    private function marcar(Model $modelo): void
    {
        match (true) {
            // Título, descripción, estado o subcategoría de la publicación.
            $modelo instanceof PublicacionWeb => $this->indice->marcarPendiente($modelo->pub_id),

            // Ficha técnica (pantalla nueva o la vieja de Especificaciones).
            $modelo instanceof EspecificacionProducto => $this->indice->marcarPendiente($modelo->esp_pubid),

            // Marca o producto base del producto.
            $modelo instanceof Producto => $this->indice->marcarVarios(
                PublicacionWeb::where('pub_prdid', $modelo->prd_id)->pluck('pub_id')
            ),

            // Nombre de la marca.
            $modelo instanceof Marca => $this->indice->marcarVarios(
                PublicacionWeb::whereIn('pub_prdid', Producto::where('prd_marid', $modelo->mar_id)->select('prd_id'))
                    ->pluck('pub_id')
            ),

            // Nombre de la categoría: afecta a sus publicaciones y, si es
            // principal, a las de sus subcategorías ("Notebooks Informática").
            $modelo instanceof Categoria, $modelo instanceof categorie => $this->indice->marcarVarios(
                PublicacionWeb::where('pub_catid', $modelo->cat_id)
                    ->orWhereIn('pub_catid', Categoria::where('cat_idpadr', $modelo->cat_id)->select('cat_id'))
                    ->pluck('pub_id')
            ),

            default => null,
        };
    }
}
