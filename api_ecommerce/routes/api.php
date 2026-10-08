<?php

use App\Http\Controllers\Admin\AtributoController;
use App\Http\Controllers\Admin\product\categorieController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Admin\Product\EspecificacionProductoController;
use App\Http\Controllers\Admin\Cupone\CuponeController;
use App\Http\Controllers\Admin\Discount\DiscountController;
use App\Http\Controllers\Api\PublicacionWebController;
use App\Http\Controllers\Api\VitrinaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Configuracion\EmpresaController;
use App\Http\Controllers\Admin\Configuracion\DepartamentoController;
use App\Http\Controllers\Admin\Configuracion\CiudadController;
use App\Http\Controllers\Admin\Configuracion\BarrioController;
use App\Http\Controllers\Admin\Configuracion\CalleController;
use App\Http\Controllers\Admin\Configuracion\SucursalController;
use App\Http\Controllers\Admin\Configuracion\DepositoController;
use App\Http\Controllers\Admin\Cliente\ClienteController;
use App\Http\Controllers\Admin\Compras\TipoIvaController;
use App\Http\Controllers\Admin\Compras\TipoComprobanteController;
use App\Http\Controllers\Admin\Configuracion\TimbradoController;
use App\Http\Controllers\Admin\Compras\TipoProveedorController;
use App\Http\Controllers\Admin\Compras\ProveedorController;
use App\Http\Controllers\Admin\Compras\ContactoProveedorController;
use App\Http\Controllers\Admin\product\MarcaController;
use App\Http\Controllers\Admin\Compras\UnidadMedidaController;
use App\Http\Controllers\Admin\product\ProductoController;
use App\Http\Controllers\Admin\Compras\CompraController;
use App\Http\Controllers\Admin\Compras\AjusteInventarioController;
use App\Http\Controllers\Admin\Compras\MovimientoStockController;
use App\Http\Controllers\Admin\Compras\StockController;
use App\Http\Controllers\Admin\Auth\RolController;
use App\Http\Controllers\Admin\product\PrecioController;
use App\Http\Controllers\Admin\Compras\TipoAjusteController;
use App\Http\Controllers\Admin\Compras\ProductoEquivalenciaController;
use App\Http\Controllers\Admin\product\ConfiguracionPrecioController;
use App\Http\Controllers\Api\PerfilClienteController;
use App\Http\Controllers\Api\DireccionClienteController;
use App\Http\Controllers\Admin\Catalogo\CategoriaAtributoController;
use App\Http\Controllers\Admin\Catalogo\FichaTecnicaController;
use App\Http\Controllers\Admin\Configuracion\ConfiguracionTiendaController;

use App\Http\Controllers\Admin\Compras\NotaCreditoController;

use App\Http\Controllers\Admin\Compras\CuotaCompraController;

use App\Http\Controllers\Admin\Compras\CuentaPorPagarController;

use App\Http\Controllers\Admin\Compras\LibroCompraController;

use App\Http\Controllers\Admin\Compras\TimbradoProveedorController;

use App\Http\Controllers\Admin\Compras\PagoCompraController;

use App\Http\Controllers\Carrito\CarritoController;

use App\Http\Controllers\Admin\Configuracion\ConfiguracionCuotaController;
use App\Http\Controllers\Chat\ChatController;
use App\Http\Controllers\Admin\Chat\FaqTiendaController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::group([
 
    //'middleware' => 'api',
    'prefix' => 'auth'
 
], function ($router) {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login_ecommerce', [AuthController::class, 'login_ecommerce'])->name('login_ecommerce');
    Route::post('/login_google', [AuthController::class, 'login_google'])->name('login_google');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/refresh', [AuthController::class, 'refresh'])->name('refresh');
    Route::post('/me', [AuthController::class, 'me'])->name('me');
    Route::post('/verified_auth', [AuthController::class, 'verified_auth'])->name('verified_auth');

    Route::post('/verified_email', [AuthController::class, 'verified_email'])->name('verified_email');
    Route::post('/verified_code', [AuthController::class, 'verified_code'])->name('verified_code');
    Route::post('/new_password', [AuthController::class, 'new_password'])->name('new_password');

});

// Catálogo público paginado — consumido por ProductoPublicoService (URL_SERVICIOS/productos, sin prefijo).
Route::get('productos', [VitrinaController::class, 'productos']);

// Rutas públicas del ecommerce (sin auth), consumidas por frontend-ecommerce.
Route::group([
    'prefix' => 'tienda',
], function ($router) {
    Route::get('home', [VitrinaController::class, 'home']);
    Route::get('sliders', [VitrinaController::class, 'sliders']);
    Route::get('producto/{slug}', [VitrinaController::class, 'detalle']);

     // NUEVO — filtro avanzado / catálogo con búsqueda:
    Route::get('productos/filtros', [VitrinaController::class, 'configFiltros']);
    Route::get('productos/buscar', [VitrinaController::class, 'buscar']);
    Route::get('categorias/menu', [VitrinaController::class, 'menuCategorias']);
	Route::get('productos/sugerencias', [VitrinaController::class, 'sugerencias'])->middleware('throttle:60,1');

    Route::get('productos/analizar-busqueda', [VitrinaController::class, 'analizarBusqueda'])->middleware('throttle:60,1');
    // Carrito de invitado: resuelve precios e imágenes sin persistir nada.
    Route::post('carrito/previsualizar', [CarritoController::class, 'previsualizar']);

    // MÓDULO 6 - ChatBot (público: invitado o cliente con JWT).
    Route::post('chat/mensajes', [ChatController::class, 'enviar'])->middleware('throttle:chat');
    Route::get('chat/{token}', [ChatController::class, 'historial'])->whereUuid('token')->middleware('throttle:60,1');
});

// Rutas privadas de tienda (requieren sesión de cliente autenticado).
Route::group([
    'middleware' => ['auth:api'],
    'prefix' => 'tienda',
], function ($router) {
    Route::post('carrito/sincronizar', [CarritoController::class, 'sincronizar']);
    Route::delete('carrito', [CarritoController::class, 'vaciar']);

    Route::apiResource('carrito', CarritoController::class)
        ->only(['index', 'store', 'update', 'destroy']);
});

Route::group([
    'middleware'=> ['auth:api', 'permiso:dashboard.ver'],   // <- solo agregar esta línea
    'prefix'=> 'admin'
], function ($router) {
    Route::get('sliders-productos', [SliderController::class, 'productos']);
    Route::get('sliders-productos/{id}', [SliderController::class, 'productoShow']);
    Route::resource('sliders', SliderController::class);
    Route::post('sliders/{id}', [SliderController::class, 'update']);

    Route::get('atributos', [AtributoController::class, 'index']);
    Route::post('atributos', [AtributoController::class, 'store']);
    Route::put('atributos/{id}', [AtributoController::class, 'update']);
    Route::delete('atributos/{id}', [AtributoController::class, 'destroy']);

    Route::post('propiedades', [AtributoController::class, 'storePropiedad']);
    Route::delete('propiedades/{id}', [AtributoController::class, 'destroyPropiedad']);

    Route::get('especificaciones', [EspecificacionProductoController::class, 'index']);
    Route::post('especificaciones', [EspecificacionProductoController::class, 'store']);
    Route::put('especificaciones/{id}', [EspecificacionProductoController::class, 'update']);
    Route::delete('especificaciones/{id}', [EspecificacionProductoController::class, 'destroy']);
	
	Route::post('especificaciones/duplicar', [EspecificacionProductoController::class, 'duplicar']);
	
	Route::get("cupones/config", [CuponeController::class, "config"]);
    Route::resource("cupones", CuponeController::class);
   
    Route::resource("discounts", DiscountController::class);

    // Módulo Configuracion: Fase 1 ---
    Route::patch('empresas/{id}/estado', [EmpresaController::class, 'toggleEstado']);
    Route::apiResource('empresas', EmpresaController::class);

    //Sucursales
    Route::get('sucursales/config', [SucursalController::class, 'config']);
    Route::patch('sucursales/{id}/estado', [SucursalController::class, 'toggleEstado']);
    Route::apiResource('sucursales', SucursalController::class);// Para Ciudad
    
    //Departamentos
    Route::get('departamentos/select', [DepartamentoController::class, 'select']);
    Route::patch('departamentos/{id}/estado', [DepartamentoController::class, 'toggleEstado']);
    Route::apiResource('departamentos', DepartamentoController::class);

    //Ciudades
    Route::get('ciudades/config', [CiudadController::class, 'config']);
    Route::get('ciudades/select', [CiudadController::class, 'select']);
    Route::patch('ciudades/{id}/estado', [CiudadController::class, 'toggleEstado']);
    Route::apiResource('ciudades', CiudadController::class);

    //Barrios
    Route::get('barrios/config', [BarrioController::class, 'config']);
    Route::get('barrios/select', [BarrioController::class, 'select']);
    Route::patch('barrios/{id}/estado', [BarrioController::class, 'toggleEstado']);
    Route::apiResource('barrios', BarrioController::class);

    //Calles
    Route::get('calles/config', [CalleController::class, 'config']);
    Route::get('calles/select', [CalleController::class, 'select']);
    Route::patch('calles/{id}/estado', [CalleController::class, 'toggleEstado']);
    Route::apiResource('calles', CalleController::class);

    Route::get('depositos/config', [DepositoController::class, 'config']);
    Route::get('depositos/select', [DepositoController::class, 'select']);
    Route::patch('depositos/{id}/estado', [DepositoController::class, 'toggleEstado']);
    Route::patch('depositos/{id}/principal', [DepositoController::class, 'marcarPrincipal']);
    Route::apiResource('depositos', DepositoController::class);

    Route::get("clientes/config", [ClienteController::class, "config"]);
    Route::get("clientes/buscar-persona", [ClienteController::class, "buscarPersona"]);
    Route::patch("clientes/{cliente}/estado", [ClienteController::class, "cambiarEstado"]);
    Route::resource("clientes", ClienteController::class)
    ->only(['index','store','update','destroy']);


    //Para Tipo_iva, Tipo_Comprobante, Timbrado
    Route::get('tipos-iva/select', [TipoIvaController::class, 'select']);
    Route::patch('tipos-iva/{id}/estado', [TipoIvaController::class, 'toggleEstado']);
    Route::apiResource('tipos-iva', TipoIvaController::class);

    Route::get('tipos-comprobante/select', [TipoComprobanteController::class, 'select']);
    Route::patch('tipos-comprobante/{id}/estado', [TipoComprobanteController::class, 'toggleEstado']);
    Route::apiResource('tipos-comprobante', TipoComprobanteController::class);

    // --- Timbrados ---
    Route::get('timbrados/config', [TimbradoController::class, 'config']);
    Route::get('timbrados/select', [TimbradoController::class, 'select']);
    Route::patch('timbrados/{id}/estado', [TimbradoController::class, 'toggleEstado']);
    Route::apiResource('timbrados', TimbradoController::class);

    // --- Modulo Compras: Fase 3 (proveedores) ---
    //--Tipo_Proveedores
    Route::get('tipos-proveedor/select', [TipoProveedorController::class, 'select']);
    Route::patch('tipos-proveedor/{id}/estado', [TipoProveedorController::class, 'toggleEstado']);
    Route::apiResource('tipos-proveedor', TipoProveedorController::class);

    //--Proveedores y Contactos
    Route::get('proveedores/config', [ProveedorController::class, 'config']);
    Route::get('proveedores/select', [ProveedorController::class, 'select']);
    Route::patch('proveedores/{id}/estado', [ProveedorController::class, 'toggleEstado']);
    Route::apiResource('proveedores', ProveedorController::class);

    // --- Contactos de proveedor (sin config/select: se usan solo desde
    //     dentro del formulario de un proveedor puntual) ---
    Route::patch('contactos-proveedor/{id}/estado', [ContactoProveedorController::class, 'toggleEstado']);
    Route::apiResource('contactos-proveedor', ContactoProveedorController::class);

    // --- Timbrados de proveedor (sub-recurso de Proveedor) ---
    Route::get('timbrados-proveedor/config', [TimbradoProveedorController::class, 'config']);
    Route::patch('timbrados-proveedor/{id}/estado', [TimbradoProveedorController::class, 'toggleEstado']);
    Route::apiResource('timbrados-proveedor', TimbradoProveedorController::class)->except('show');

    // --- Unidades de medida ---
    Route::get('unidades-medida/select', [UnidadMedidaController::class, 'select']);
    Route::patch('unidades-medida/{id}/estado', [UnidadMedidaController::class, 'toggleEstado']);
    Route::apiResource('unidades-medida', UnidadMedidaController::class);

    // --- Productos ---
    Route::get('productos/config', [ProductoController::class, 'config']);
    Route::get('ecommerce/menus', [VitrinaController::class, 'menus']);
    Route::get('productos/select', [ProductoController::class, 'select']);
    Route::patch('productos/{id}/estado', [ProductoController::class, 'toggleEstado']);
    Route::apiResource('productos', ProductoController::class);
    
    // --- Equivalencias de unidad por producto (sin config/select: se usan
    //solo desde dentro del formulario de un producto puntual) ---
    Route::patch('producto-equivalencias/{id}/estado', [ProductoEquivalenciaController::class, 'toggleEstado']);
    Route::apiResource('producto-equivalencias', ProductoEquivalenciaController::class)->except(['show']);

    // --- Configuración de Cuotas por producto (Gestión de Cuotas) ---
    Route::get('configuraciones-cuota/productos', [ConfiguracionCuotaController::class, 'productos']);
    Route::patch('configuraciones-cuota/{id}/estado', [ConfiguracionCuotaController::class, 'toggleEstado']);
    Route::apiResource('configuraciones-cuota', ConfiguracionCuotaController::class)->except(['show']);

    // --- Publicación Web (ficha pública de un producto) ---
    Route::get('productos/{producto}/publicacion', [PublicacionWebController::class, 'show']);
    Route::post('productos/{producto}/publicacion', [PublicacionWebController::class, 'guardar']);
    Route::delete('productos/{producto}/publicacion/imagenes/{imagen}', [PublicacionWebController::class, 'eliminarImagen']);
    Route::get('productos/{producto}/ficha-tecnica', [FichaTecnicaController::class, 'show']);


    // --- Compras (modulo transaccional: sin apiResource, sin toggleEstado) ---
    Route::get('compras/config', [CompraController::class, 'config']);
    Route::get('compras', [CompraController::class, 'index']);
    Route::get('compras/{id}', [CompraController::class, 'show']);
    Route::post('compras', [CompraController::class, 'store']);
    Route::patch('compras/{id}/anular', [CompraController::class, 'anular']);

    //--Ajuste Inventario
    Route::get('ajustes-inventario/config', [AjusteInventarioController::class, 'config']);
    Route::get('ajustes-inventario', [AjusteInventarioController::class, 'index']);
    Route::get('ajustes-inventario/{id}', [AjusteInventarioController::class, 'show']);
    Route::post('ajustes-inventario', [AjusteInventarioController::class, 'store']);
    Route::patch('ajustes-inventario/{id}/confirmar', [AjusteInventarioController::class, 'confirmar']);
    Route::patch('ajustes-inventario/{id}/anular', [AjusteInventarioController::class, 'anular']);
    Route::delete('ajustes-inventario/{id}', [AjusteInventarioController::class, 'destroy']);

    // --- Movimiento_Stock (solo lectura)
    Route::get('movimientos-stock/config', [MovimientoStockController::class, 'config']);
    Route::get('movimientos-stock', [MovimientoStockController::class, 'index']);

    // --- Stock_actual (solo lectura)
    Route::get('stock/config', [StockController::class, 'config']);
    Route::get('stock', [StockController::class, 'index']);

    Route::get('roles/config', [RolController::class, 'config']);
    Route::patch('roles/{id}/estado', [RolController::class, 'toggleEstado']);
    Route::apiResource('roles', RolController::class);

    //Precios
    Route::get('precios', [PrecioController::class, 'index']);
    Route::get('precios/{id}', [PrecioController::class, 'show']);
    Route::patch('precios/{id}', [PrecioController::class, 'update']);

    Route::get('tipos-ajuste/select', [TipoAjusteController::class, 'select']);
    Route::patch('tipos-ajuste/{id}/estado', [TipoAjusteController::class, 'toggleEstado']);
    Route::apiResource('tipos-ajuste', TipoAjusteController::class);

    // Configuración general de precios (singleton: solo show/update)
    Route::get('configuracion-precio', [ConfiguracionPrecioController::class, 'show']);
    Route::patch('configuracion-precio', [ConfiguracionPrecioController::class, 'update']);
 
    
    // Configuración general de la tienda (singleton: avisos de stock)
    Route::get('configuracion-tienda', [ConfiguracionTiendaController::class, 'show']);
    Route::patch('configuracion-tienda', [ConfiguracionTiendaController::class, 'update']);

    // junto al bloque de "Compras":
    Route::get('compras/{id}/notas-credito/config', [NotaCreditoController::class, 'config']);
    Route::get('compras/{id}/notas-credito', [NotaCreditoController::class, 'index']);
    Route::post('compras/{id}/notas-credito', [NotaCreditoController::class, 'store']);

    // --- Cuotas de credito de Compras ---
    Route::get('compras/{id}/cuotas', [CuotaCompraController::class, 'index']);

    // --- Cuentas por Pagar (reporte transversal sobre cuota_compra) ---
    Route::get('cuentas-por-pagar/config', [CuentaPorPagarController::class, 'config']);
    Route::get('cuentas-por-pagar', [CuentaPorPagarController::class, 'index']);

    // --- Libro de Compras (reporte transversal: FC + NC) ---
    Route::get('libro-compra/config', [LibroCompraController::class, 'config']);
    Route::get('libro-compra', [LibroCompraController::class, 'index']);

    // --- Pagos de compra (1 pago = 1 cuota) ---
    Route::post('pagos-compra', [PagoCompraController::class, 'store']);
    Route::patch('pagos-compra/{id}/anular', [PagoCompraController::class, 'anular']);
    
    // --- Plantillas de Ficha Técnica ---
    Route::get('categoria-atributos/categorias', [CategoriaAtributoController::class, 'categorias']);
    Route::get('categoria-atributos/config', [CategoriaAtributoController::class, 'config']);
    Route::apiResource('categoria-atributos', CategoriaAtributoController::class)->except(['show']);

	 // --- MÓDULO 6 - ChatBot: información de la tienda ---
    Route::middleware('permiso:chatbot.gestionar')->group(function () {
    Route::get('faq-tienda/config', [FaqTiendaController::class, 'config']);
    Route::patch('faq-tienda/{id}/estado', [FaqTiendaController::class, 'toggleEstado']);
    Route::apiResource('faq-tienda', FaqTiendaController::class)->except(['show'])->parameters(['faq-tienda' => 'id']);
    });
});

Route::prefix('admin/usuarios')->middleware('auth:api')->group(function () {
    Route::get('/catalogos', [UsuarioController::class, 'catalogos'])
        ->middleware('permiso:usuarios.ver');

    Route::get('/', [UsuarioController::class, 'index'])
        ->middleware('permiso:usuarios.ver');

    Route::get('/{usuario}', [UsuarioController::class, 'show'])
        ->middleware('permiso:usuarios.ver');

    Route::post('/', [UsuarioController::class, 'store'])
        ->middleware('permiso:usuarios.gestionar');

    Route::put('/{usuario}', [UsuarioController::class, 'update'])
        ->middleware('permiso:usuarios.gestionar');

    Route::patch('/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])
        ->middleware('permiso:usuarios.gestionar');
        
});

// Rutas privadas del ecommerce (requieren sesión de Cliente).
// Van en grupo aparte del "tienda" público de arriba (home/sliders/producto)
// porque este sí necesita auth:api.
Route::group([
    'middleware' => ['auth:api'],
    'prefix' => 'tienda/perfil',
], function () {
    Route::get('/', [PerfilClienteController::class, 'show']);
    Route::post('/', [PerfilClienteController::class, 'update']);
    Route::patch('/password', [PerfilClienteController::class, 'actualizarPassword']);

    Route::get('/direcciones/catalogos', [DireccionClienteController::class, 'catalogos']);
    Route::get('/direcciones', [DireccionClienteController::class, 'index']);
    Route::post('/direcciones', [DireccionClienteController::class, 'store']);
    Route::put('/direcciones/{id}', [DireccionClienteController::class, 'update']);
    Route::delete('/direcciones/{id}', [DireccionClienteController::class, 'destroy']);
    Route::patch('/direcciones/{id}/predeterminada', [DireccionClienteController::class, 'marcarPredeterminada']);
});

Route::get('publicaciones', [PublicacionWebController::class, 'index']);

Route::apiResource('categorias', categorieController::class);


Route::get('marcas/select', [MarcaController::class, 'select']);
Route::patch('marcas/{id}/estado', [MarcaController::class, 'toggleEstado']);
Route::apiResource('marcas', MarcaController::class);