<?php

use App\Http\Controllers\Admin\AtributoController;
use App\Http\Controllers\Admin\product\categorieController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Admin\Product\VariacionProductoController;
use App\Http\Controllers\Admin\Product\EspecificacionProductoController;
use App\Http\Controllers\Admin\Cupone\CuponeController;
use App\Http\Controllers\Admin\Discount\DiscountController;
use App\Http\Controllers\AuthController;
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
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/refresh', [AuthController::class, 'refresh'])->name('refresh');
    Route::post('/me', [AuthController::class, 'me'])->name('me');
    Route::post('/verified_auth', [AuthController::class, 'verified_auth'])->name('verified_auth');

    Route::post('/verified_email', [AuthController::class, 'verified_email'])->name('verified_email');
    Route::post('/verified_code', [AuthController::class, 'verified_code'])->name('verified_code');
    Route::post('/new_password', [AuthController::class, 'new_password'])->name('new_password');

});

Route::group([
    'prefix' => 'admin'
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

    Route::get('variaciones/config', [VariacionProductoController::class, 'config']);
    Route::get('variaciones', [VariacionProductoController::class, 'index']);
    Route::post('variaciones', [VariacionProductoController::class, 'store']);
    Route::put('variaciones/{id}', [VariacionProductoController::class, 'update']);
    Route::delete('variaciones/{id}', [VariacionProductoController::class, 'destroy']);

    Route::get('especificaciones', [EspecificacionProductoController::class, 'index']);
    Route::post('especificaciones', [EspecificacionProductoController::class, 'store']);
    Route::put('especificaciones/{id}', [EspecificacionProductoController::class, 'update']);
    Route::delete('especificaciones/{id}', [EspecificacionProductoController::class, 'destroy']);
	
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

    // --- Unidades de medida ---
    Route::get('unidades-medida/select', [UnidadMedidaController::class, 'select']);
    Route::patch('unidades-medida/{id}/estado', [UnidadMedidaController::class, 'toggleEstado']);
    Route::apiResource('unidades-medida', UnidadMedidaController::class);

    // --- Productos ---
    Route::get('productos/config', [ProductoController::class, 'config']);
    Route::get('productos/select', [ProductoController::class, 'select']);
    Route::patch('productos/{id}/estado', [ProductoController::class, 'toggleEstado']);
    Route::apiResource('productos', ProductoController::class);

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
    });



Route::apiResource('categorias', categorieController::class);


Route::get('marcas/select', [MarcaController::class, 'select']);
Route::patch('marcas/{id}/estado', [MarcaController::class, 'toggleEstado']);
Route::apiResource('marcas', MarcaController::class);