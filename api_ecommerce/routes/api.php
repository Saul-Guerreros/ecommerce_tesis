<?php

use App\Http\Controllers\Admin\AtributoController;
use App\Http\Controllers\Admin\product\categorieController;
use App\Http\Controllers\Admin\product\marcaController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Admin\Product\VariacionProductoController;
use App\Http\Controllers\Admin\Product\EspecificacionProductoController;
use App\Http\Controllers\Admin\Cupone\CuponeController;
use App\Http\Controllers\Admin\Discount\DiscountController;
use App\Http\Controllers\Api\PublicacionWebController;
use App\Http\Controllers\Api\TipoIvaController;
use App\Http\Controllers\Api\UnidadMedidaController;
use App\Http\Controllers\Api\VitrinaController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



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
	
	Route::post('especificaciones/duplicar', [EspecificacionProductoController::class, 'duplicar']);
	
	Route::get("cupones/config", [CuponeController::class, "config"]);
    Route::resource("cupones", CuponeController::class);
   
    Route::resource("discounts", DiscountController::class);

});

Route::prefix('admin/usuarios')->middleware('auth:api')->group(function () {
    Route::get('/catalogos', [UsuarioController::class, 'catalogos'])->middleware('permiso:usuarios.ver');
    Route::get('/', [UsuarioController::class, 'index'])->middleware('permiso:usuarios.ver');
    Route::get('/{usuario}', [UsuarioController::class, 'show'])->middleware('permiso:usuarios.ver');
    Route::post('/', [UsuarioController::class, 'store'])->middleware('permiso:usuarios.gestionar');
    Route::put('/{usuario}', [UsuarioController::class, 'update'])->middleware('permiso:usuarios.gestionar');
    Route::patch('/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])->middleware('permiso:usuarios.gestionar');
});

Route::prefix('tienda')->group(function () {
    Route::get('home', [VitrinaController::class, 'home']);
    Route::get('producto/{slug}', [VitrinaController::class, 'detalle']);
	Route::get('sliders', [VitrinaController::class, 'sliders']);
});

Route::prefix('productos')->group(function () {
    Route::get('/', [ProductoController::class, 'index']);
    Route::post('/', [ProductoController::class, 'store']);
    Route::get('/{producto}', [ProductoController::class, 'show']);
    Route::match(['put', 'post'], '/{producto}', [ProductoController::class, 'update']);
    Route::delete('/{producto}', [ProductoController::class, 'destroy']);
    //Route::delete('/{producto}/imagenes/{imagen}', [ProductoController::class, 'eliminarImagen']);


    Route::get('/{producto}/publicacion', [PublicacionWebController::class, 'show']);
    Route::post('/{producto}/publicacion', [PublicacionWebController::class, 'guardar']);
    Route::delete('/{producto}/publicacion/imagenes/{imagen}', [PublicacionWebController::class, 'eliminarImagen']);
});

Route::get('publicaciones', [PublicacionWebController::class, 'index']);

Route::apiResource('categorias', categorieController::class);
Route::apiResource('marcas', marcaController::class);

Route::get('tipos-iva', [TipoIvaController::class, 'index']);
Route::get('unidades-medida', [UnidadMedidaController::class, 'index']);