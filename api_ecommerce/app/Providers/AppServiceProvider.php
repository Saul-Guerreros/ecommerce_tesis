<?php

namespace App\Providers;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Product\EspecificacionProducto;
use App\Models\product\categorie;
use App\Models\Producto;
use App\Models\PublicacionWeb;
use App\Observers\IndiceBusquedaObserver;
use App\Services\Catalogo\IndiceBusquedaService;
use Illuminate\Support\ServiceProvider;
use App\Services\Chat\Herramientas\{BuscarProductos, ProponerAgregarCarrito,RegistroHerramientas, SolicitarAtencionHumana, VerProducto};

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton: los pendientes de reindexar se acumulan en UNA instancia
        // durante toda la petición y se procesan una sola vez al final.
        $this->app->singleton(IndiceBusquedaService::class);
        $this->app->tag([BuscarProductos::class,VerProducto::class,ProponerAgregarCarrito::class,SolicitarAtencionHumana::class,], 'chat.herramientas');

        $this->app->bind(RegistroHerramientas::class,
            fn ($app) => new RegistroHerramientas($app->tagged('chat.herramientas')));
        }

    public function boot(): void
    {
        // Modelos que alimentan el índice de búsqueda de la tienda.
        // (Hay dos modelos sobre la tabla categoria; se observan ambos.)
        foreach ([
            PublicacionWeb::class,
            EspecificacionProducto::class,
            Producto::class,
            Marca::class,
            Categoria::class,
            categorie::class,
        ] as $modelo) {
            $modelo::observe(IndiceBusquedaObserver::class);
        }
    }
}

