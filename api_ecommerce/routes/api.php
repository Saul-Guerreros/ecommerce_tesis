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
use App\Http\Controllers\Admin\product\marcaController;
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
use App\Http\Controllers\Admin\Cliente\TipoClienteController;
use App\Http\Controllers\Admin\Funcionario\CargoController;
use App\Http\Controllers\Admin\Funcionario\FuncionarioController;
use App\Http\Controllers\Admin\Seguridad\AuditoriaController;
use App\Http\Controllers\Admin\Configuracion\ConfiguracionCuotaController;
use App\Http\Controllers\Chat\ChatController;
use App\Http\Controllers\Admin\Chat\FaqTiendaController;
use App\Http\Controllers\Admin\Perfil\PerfilController as PerfilAdminController;
use App\Http\Controllers\Admin\Auth\PermisoController;
use App\Http\Controllers\Pedido\CheckoutController;

use App\Http\Controllers\Credito\SolicitudCreditoController;
use App\Http\Controllers\Api\FavoritoController;

use App\Http\Controllers\Admin\Configuracion\ConfiguracionEnvioController;
use App\Http\Controllers\Admin\Configuracion\TipoDocumentoController;
use App\Http\Controllers\Admin\Configuracion\TarifaEnvioController;

use App\Http\Controllers\Api\DatoFiscalClienteController;

use App\Http\Controllers\Admin\Pedido\PedidoController;

use App\Http\Controllers\Admin\Pedido\PedidoGestionController;

use App\Http\Controllers\Admin\Servicio\TipoDispositivoController;
use App\Http\Controllers\Admin\Servicio\FallaController;
use App\Http\Controllers\Admin\Servicio\TrabajoController;
use App\Http\Controllers\Servicio\ServicioClienteController;
use App\Http\Controllers\Admin\Servicio\SolicitudServicioController;
use App\Http\Controllers\Admin\Servicio\MostradorServicioController;
use App\Http\Controllers\Admin\Servicio\OrdenTecnicoController;
use App\Http\Controllers\Admin\Servicio\PresupuestoValidacionController;
use App\Http\Controllers\Admin\Servicio\PanelServicioController;
use App\Http\Controllers\Admin\Servicio\CobroServicioController;
use App\Http\Controllers\Servicio\PagoServicioController;
use App\Http\Controllers\Servicio\FacturaServicioClienteController;
use App\Http\Controllers\Admin\Servicio\FacturaServicioController;
use App\Http\Controllers\Admin\Reporte\ReporteConfigController;
use App\Http\Controllers\Admin\Reporte\DashboardController;
use App\Http\Controllers\Admin\Reporte\ReporteVentasController;
use App\Http\Controllers\Admin\Reporte\ReporteInventarioController;
use App\Http\Controllers\Admin\Reporte\ReporteExportacionController;
use App\Http\Controllers\Admin\Reporte\ReporteComprasController;
use App\Http\Controllers\Admin\Reporte\ReporteServicioController;
use App\Http\Controllers\Admin\Reporte\ReporteClientesController;
use App\Http\Controllers\Admin\Reporte\ReportePromocionesController;
use App\Http\Controllers\Admin\Reporte\ReporteChatbotController;
use App\Http\Controllers\Pedido\PagoPedidoController;
use App\Http\Controllers\Servicio\DiagnosticoPreliminarController;
use App\Http\Controllers\Pedido\PedidoClienteController;
use App\Http\Controllers\Admin\Reporte\ReporteComportamientoController;
use App\Http\Controllers\Api\ActividadController;
use App\Http\Controllers\Admin\Venta\VentaController;
use App\Http\Controllers\Admin\Servicio\ClasificacionSolicitudController;
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
    Route::get('producto/{slug}/stock-sucursales', [VitrinaController::class, 'stockSucursales']);   // <-- nueva

     // NUEVO — filtro avanzado / catálogo con búsqueda:
    Route::get('productos/filtros', [VitrinaController::class, 'configFiltros']);
    Route::get('productos/buscar', [VitrinaController::class, 'buscar']);
    Route::get('categorias/menu', [VitrinaController::class, 'menuCategorias']);
	Route::get('productos/sugerencias', [VitrinaController::class, 'sugerencias'])->middleware('throttle:60,1');
	
	// Comparador de productos (hasta 4 slugs separados por coma).
    Route::get('productos/comparar', [VitrinaController::class, 'comparar'])->middleware('throttle:60,1');

    Route::get('productos/analizar-busqueda', [VitrinaController::class, 'analizarBusqueda'])->middleware('throttle:60,1');
    // Carrito de invitado: resuelve precios e imágenes sin persistir nada.
    Route::post('carrito/previsualizar', [CarritoController::class, 'previsualizar']);

    // Favoritos de invitado: resuelve productos desde ids guardados en el navegador.
    Route::post('favoritos/previsualizar', [FavoritoController::class, 'previsualizar'])->middleware('throttle:60,1');

    // MÓDULO 6 - ChatBot (público: invitado o cliente con JWT).
    Route::post('chat/mensajes', [ChatController::class, 'enviar'])->middleware('throttle:chat');
    Route::get('chat/{token}', [ChatController::class, 'historial'])->whereUuid('token')->middleware('throttle:60,1');

    // Análisis de comportamiento: vista de producto (invitado o cliente con JWT).
    Route::post('actividad/vista', [ActividadController::class, 'vista'])->middleware('throttle:60,1');
    // Catálogo de estados de Solicitud de Crédito (público: se usa para
    // leyendas antes de loguear, ej. en "Mis Solicitudes").
    Route::get('credito/estados', [SolicitudCreditoController::class, 'estados']);

});

// Rutas privadas de tienda (requieren sesión de cliente autenticado).
Route::group([
    'middleware' => ['auth:api'],
    'prefix' => 'tienda',
], function ($router) {
    Route::post('carrito/sincronizar', [CarritoController::class, 'sincronizar']);
    Route::delete('carrito', [CarritoController::class, 'vaciar']);

    // --- Favoritos (wishlist) ---
    Route::get('favoritos/ids', [FavoritoController::class, 'ids']);
    Route::post('favoritos/sincronizar', [FavoritoController::class, 'sincronizar']);
    Route::get('favoritos', [FavoritoController::class, 'index']);
    Route::post('favoritos', [FavoritoController::class, 'store']);
    Route::delete('favoritos/{producto}', [FavoritoController::class, 'destroy'])->whereNumber('producto');
    // Debe ir ANTES del apiResource: si no, 'carrito/{carrito}' (destroy)
    // la intercepta y Laravel intenta castear "modalidad" como car_id.
    Route::delete('carrito/modalidad/{modalidad}', [CarritoController::class, 'vaciarModalidad']);

    Route::apiResource('carrito', CarritoController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    // --- Checkout (módulo transaccional M3) ---
    // Paso 1: Entrega
    Route::get('checkout/entrega',  [CheckoutController::class, 'configEntrega']);
    Route::post('checkout/entrega', [CheckoutController::class, 'guardarEntrega']);

    
    // --- Paso 3: Confirmación ---
    Route::get('checkout/confirmacion', [CheckoutController::class, 'configConfirmacion']);   // <-- nuevo
    Route::post('checkout/confirmar',   [CheckoutController::class, 'confirmar']);            // <-- nuevo
   
	// --- Servicio técnico (M5) ---
    Route::get('servicios/config', [ServicioClienteController::class, 'config']);
    Route::get('servicios/costo-retiro/{dirId}', [ServicioClienteController::class, 'costoRetiro'])->whereNumber('dirId');
    Route::get('servicios', [ServicioClienteController::class, 'index']);
    Route::post('servicios', [ServicioClienteController::class, 'store'])->middleware('throttle:10,1');
	Route::get('servicios/{numero}/constancia/{tipo}', [ServicioClienteController::class, 'constancia'])
        ->where('numero', 'SS-[0-9]{4}-[0-9]+')->whereIn('tipo', ['recepcion', 'retiro']);
    Route::get('servicios/{numero}', [ServicioClienteController::class, 'show'])->where('numero', 'SS-[0-9]{4}-[0-9]+');
	Route::post('servicios/diagnostico-preliminar', [DiagnosticoPreliminarController::class, 'cliente'])->middleware('throttle:6,1');

	Route::post('servicios/{numero}/presupuesto/aprobar', [ServicioClienteController::class, 'aprobarPresupuesto'])
        ->where('numero', 'SS-[0-9]{4}-[0-9]+');
    Route::post('servicios/{numero}/presupuesto/rechazar', [ServicioClienteController::class, 'rechazarPresupuesto'])
        ->where('numero', 'SS-[0-9]{4}-[0-9]+');

    // --- Pago del servicio (C4). Espejo de tienda/pedidos/{numero}/pago ---
    Route::prefix('servicios/{numero}')->where(['numero' => 'SS-[0-9]{4}-[0-9]+'])->group(function () {
        Route::get('pago', [PagoServicioController::class, 'config']);
        Route::post('pago', [PagoServicioController::class, 'registrar'])->middleware('throttle:10,1');
        // GET descarga el comprobante, POST lo reemplaza (pago rechazado)
        Route::get('pagos/{pago}/comprobante', [PagoServicioController::class, 'comprobante'])->whereNumber('pago');
        Route::post('pagos/{pago}/comprobante', [PagoServicioController::class, 'reemplazarComprobante'])->whereNumber('pago');

        // Facturación del servicio: CI/RUC antes de pagar y descarga del KuDE
        Route::get('facturacion', [FacturaServicioClienteController::class, 'show']);
        Route::put('facturacion', [FacturaServicioClienteController::class, 'update'])->middleware('throttle:20,1');
        Route::get('factura/kude', [FacturaServicioClienteController::class, 'kude']);
    });

    // Paso 2: Facturación + Confirmar Pedido (crea el pedido y reserva stock)
    Route::get('checkout/facturacion',  [CheckoutController::class, 'configFacturacion']);
    Route::post('checkout/facturacion', [CheckoutController::class, 'guardarFacturacion']);
    Route::post('checkout/confirmar',   [CheckoutController::class, 'confirmar']);

    // línea 186
    // --- Mis Pedidos + Paso 3 (Pago de un pedido ya creado) ---
    Route::prefix('pedidos')->group(function () {          // 187
    Route::get('pendientes', [PedidoClienteController::class, 'pendientes']);
        Route::get('/', [PedidoClienteController::class, 'index']);
        Route::get('{numero}', [PedidoClienteController::class, 'show']);
        Route::get('{numero}/pagos/{pago}/comprobante', [PedidoClienteController::class, 'comprobante']);

        Route::get('{numero}/pago', [PagoPedidoController::class, 'config']);       // 193
        Route::post('{numero}/pago', [PagoPedidoController::class, 'registrar']);    // 194
        Route::get('{numero}/resultado', [PagoPedidoController::class, 'resultado']); // 195

        // Reemplazo del comprobante de un pago rechazado. Misma URI que el
        // GET del comprobante, distinto verbo: el GET lo descarga, el POST
        // lo sustituye.
        Route::post('{numero}/pagos/{pago}/comprobante', [PagoPedidoController::class, 'reemplazarComprobante']); // 200
    });                                                       // 201

    // --- Solicitud de Crédito (continúa el flujo del Carrito a Crédito) ---
    Route::get('credito/config', [SolicitudCreditoController::class, 'config']);

    Route::post('credito/personal',    [SolicitudCreditoController::class, 'guardarPersonal']);
    Route::post('credito/laboral',     [SolicitudCreditoController::class, 'guardarLaboral']);
    Route::post('credito/referencias', [SolicitudCreditoController::class, 'guardarReferencias']);

    Route::get('credito/confirmacion', [SolicitudCreditoController::class, 'configConfirmacion']);
    Route::post('credito/generar',     [SolicitudCreditoController::class, 'generar']);

    Route::get('credito/solicitudes',      [SolicitudCreditoController::class, 'index']);
    Route::get('credito/solicitudes/{id}', [SolicitudCreditoController::class, 'show']);

 });
 
 // Servicio técnico - Bandeja de solicitudes (A1) y recepción (A2)
Route::prefix('admin/servicios/solicitudes')->middleware('auth:api')->group(function () {
    // Detalle: lo pueden ver la bandeja (solicitudes.ver) y el panel general (panel.ver).
    // Va fuera del grupo con 'permiso:servicios.solicitudes.ver' porque los middleware
    // de un grupo se acumulan (AND) y bloquearían a quien solo tiene panel.ver.
    Route::get('/{id}', [SolicitudServicioController::class, 'show'])
        ->whereNumber('id')
        ->middleware('permiso:servicios.solicitudes.ver,servicios.panel.ver');

    Route::middleware('permiso:servicios.solicitudes.ver')->group(function () {
        Route::get('/config', [SolicitudServicioController::class, 'config']);
        Route::get('/', [SolicitudServicioController::class, 'index']);
        Route::post('/{id}/agendar-retiro', [SolicitudServicioController::class, 'agendarRetiro'])->whereNumber('id');
        Route::post('/{id}/en-transito', [SolicitudServicioController::class, 'enTransito'])->whereNumber('id');
        Route::post('/{id}/rechazar', [SolicitudServicioController::class, 'rechazar'])->whereNumber('id');
        Route::post('/{id}/recepcion', [SolicitudServicioController::class, 'confirmarRecepcion'])->whereNumber('id');

        // Ingreso presencial (mostrador): alta rápida de cliente + solicitud recibida en una transacción
        Route::prefix('mostrador')->group(function () {
            Route::get('/config', [MostradorServicioController::class, 'config']);
            Route::get('/clientes', [MostradorServicioController::class, 'buscarClientes']);
            Route::post('/diagnostico-preliminar', [DiagnosticoPreliminarController::class, 'mostrador'])->middleware('throttle:20,1');
            Route::post('/clientes', [MostradorServicioController::class, 'crearCliente']);
            Route::put('/clientes/{cliId}/documento', [MostradorServicioController::class, 'completarDocumento'])->whereNumber('cliId');
            Route::get('/clientes/{cliId}/garantias', [MostradorServicioController::class, 'garantias'])->whereNumber('cliId');
            Route::get('/clientes/{cliId}/reclamables', [MostradorServicioController::class, 'reclamables'])->whereNumber('cliId');
            Route::get('/reclamo/{numero}', [MostradorServicioController::class, 'reclamo'])->where('numero', 'SS-[0-9]{4}-[0-9]+');
            Route::post('/', [MostradorServicioController::class, 'store']);
        });
    });
});

// Punto 7 - Clasificación automática de solicitudes (IA): consulta, corrección y reclasificación.
// La usan la bandeja (solicitudes.ver) y el técnico (tecnico.ver).
Route::prefix('admin/servicios/clasificacion')
    ->middleware(['auth:api', 'permiso:servicios.solicitudes.ver,servicios.tecnico.ver'])
    ->group(function () {
        Route::get('/config', [ClasificacionSolicitudController::class, 'config']);
        Route::get('/{sseId}', [ClasificacionSolicitudController::class, 'show'])->whereNumber('sseId');
        Route::put('/{sseId}', [ClasificacionSolicitudController::class, 'update'])->whereNumber('sseId');
        Route::post('/{sseId}/confirmar', [ClasificacionSolicitudController::class, 'confirmar'])->whereNumber('sseId');
        Route::post('/{sseId}/reclasificar', [ClasificacionSolicitudController::class, 'reclasificar'])
            ->whereNumber('sseId')->middleware('throttle:10,1');
    });

Route::group([
    'middleware'=> ['auth:api', 'permiso:dashboard.ver'],   // <- solo agregar esta línea
    'prefix'=> 'admin'
], function ($router) {
    Route::get('sliders-destinos', [SliderController::class, 'destinos']);
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
	Route::get('discounts/producto/{id}/vigentes', [DiscountController::class, 'vigentesPorProducto']);

    // Módulo Configuracion: Fase 1 ---
    Route::patch('empresas/{id}/estado', [EmpresaController::class, 'toggleEstado']);
    Route::apiResource('empresas', EmpresaController::class);

    //Sucursales
    Route::get('sucursales/config', [SucursalController::class, 'config']);
    Route::get('sucursales/select', [SucursalController::class, 'select']);   // <-- agregar
    Route::patch('sucursales/{id}/estado', [SucursalController::class, 'toggleEstado']);
    Route::apiResource('sucursales', SucursalController::class)
    ->parameters(['sucursales' => 'sucursal']); // fuerza el nombre del parámetro a 'sucursal' Para Ciudad
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

     // --- Tipos de documento ---
    Route::get('tipos-documento/select', [TipoDocumentoController::class, 'select']);
    Route::patch('tipos-documento/{id}/estado', [TipoDocumentoController::class, 'toggleEstado']);
    Route::apiResource('tipos-documento', TipoDocumentoController::class)->parameters(['tipos-documento' => 'id']);

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

    // --- Tipos de cliente ---
    Route::get('tipos-cliente/select', [TipoClienteController::class, 'select']);
    Route::patch('tipos-cliente/{id}/estado', [TipoClienteController::class, 'toggleEstado']);
    Route::apiResource('tipos-cliente', TipoClienteController::class)->parameters(['tipos-cliente' => 'id']);

    Route::get('cargos/select', [CargoController::class, 'select']);
    Route::patch('cargos/{id}/estado', [CargoController::class, 'toggleEstado']);
    Route::apiResource('cargos', CargoController::class)->parameters(['cargos' => 'id']);

    Route::get('funcionarios/config', [FuncionarioController::class, 'config']);
    Route::get('funcionarios/buscar-persona', [FuncionarioController::class, 'buscarPersona']);
    Route::patch('funcionarios/{id}/estado', [FuncionarioController::class, 'cambiarEstado']);
    Route::apiResource('funcionarios', FuncionarioController::class)->only(['index', 'store', 'update', 'destroy']) ->parameters(['funcionarios' => 'id']);

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

	// --- Permisos por rol (matriz de acceso a las opciones del panel) ---
    Route::middleware('permiso:permisos.gestionar')->group(function () {
        Route::get('permisos/matriz', [PermisoController::class, 'matriz']);
        Route::put('permisos/matriz', [PermisoController::class, 'guardar']);
    });


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

    // Configuración de envío (singleton: gratis / fijo / sucursal)
    Route::get('configuracion-envio', [ConfiguracionEnvioController::class, 'show']);
    Route::patch('configuracion-envio', [ConfiguracionEnvioController::class, 'update']);

    // Tarifas por sucursal + ciudad (solo relevante cuando el modo es "sucursal")
    Route::apiResource('tarifas-envio', TarifaEnvioController::class)->except(['show']);

    // --- Auditoría (solo lectura). Reservada a quien tenga 'usuarios.ver' (el Vendedor no lo tiene) ---
    Route::middleware('permiso:auditoria.ver')->prefix('seguridad')->group(function () { 
        Route::get('historial-password', [AuditoriaController::class, 'historialPassword']);
        Route::get('sesiones',           [AuditoriaController::class, 'sesiones']);
        Route::get('intentos-sesion',    [AuditoriaController::class, 'intentosSesion']);
        Route::get('tokens',             [AuditoriaController::class, 'tokens']);
    });
    
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
	
	
	// ===== Servicio Técnico - Catálogos =====
    Route::middleware('permiso:tipos-dispositivo.ver')->group(function () {
        Route::get('tipos-dispositivo/select', [TipoDispositivoController::class, 'select']);
        Route::patch('tipos-dispositivo/{id}/estado', [TipoDispositivoController::class, 'toggleEstado']);
        Route::apiResource('tipos-dispositivo', TipoDispositivoController::class)->parameters(['tipos-dispositivo' => 'id']);
    });

    // select también lo usa el técnico en el diagnóstico (T2)
    Route::get('fallas/select', [FallaController::class, 'select'])
        ->middleware('permiso:fallas.ver,servicios.tecnico.ver');
    Route::middleware('permiso:fallas.ver')->group(function () {
        Route::get('fallas/config', [FallaController::class, 'config']);
        Route::patch('fallas/{id}/estado', [FallaController::class, 'toggleEstado']);
        Route::apiResource('fallas', FallaController::class)->parameters(['fallas' => 'id']);
    });

    // select también lo usa el técnico en el presupuesto (T3)
    Route::get('trabajos/select', [TrabajoController::class, 'select'])
        ->middleware('permiso:trabajos.ver,servicios.tecnico.ver');
    Route::middleware('permiso:trabajos.ver')->group(function () {
        Route::get('trabajos/config', [TrabajoController::class, 'config']);
        Route::patch('trabajos/{id}/estado', [TrabajoController::class, 'toggleEstado']);
        Route::apiResource('trabajos', TrabajoController::class)->parameters(['trabajos' => 'id']);
    });
});

// Servicio técnico - Ruta exclusiva del técnico (T1, T2)
Route::prefix('admin/servicios/tecnico/ordenes')
    ->middleware(['auth:api', 'permiso:servicios.tecnico.ver'])
    ->group(function () {
        Route::get('/', [OrdenTecnicoController::class, 'index']);
        Route::get('/{sseId}', [OrdenTecnicoController::class, 'show'])->whereNumber('sseId');
        Route::get('/{sseId}/config', [OrdenTecnicoController::class, 'config'])->whereNumber('sseId');
        Route::get('/{sseId}/clave', [OrdenTecnicoController::class, 'clave'])->whereNumber('sseId');
        Route::post('/{sseId}/iniciar', [OrdenTecnicoController::class, 'iniciar'])->whereNumber('sseId');
        Route::post('/{sseId}/diagnostico', [OrdenTecnicoController::class, 'diagnostico'])->whereNumber('sseId');
        Route::post('/{sseId}/no-reparable', [OrdenTecnicoController::class, 'noReparable'])->whereNumber('sseId');

        // Garantía de compra: el taller gestiona el reclamo con la empresa responsable
        Route::post('/{sseId}/garantia/iniciar', [OrdenTecnicoController::class, 'iniciarGarantia'])->whereNumber('sseId');
        Route::put('/{sseId}/garantia', [OrdenTecnicoController::class, 'guardarGarantia'])->whereNumber('sseId');
        Route::post('/{sseId}/garantia/enviar', [OrdenTecnicoController::class, 'enviarGarantia'])->whereNumber('sseId');
        Route::post('/{sseId}/garantia/resolver', [OrdenTecnicoController::class, 'resolverGarantia'])->whereNumber('sseId');
		
		Route::get('/{sseId}/productos', [OrdenTecnicoController::class, 'productos'])->whereNumber('sseId');
        Route::put('/{sseId}/presupuesto', [OrdenTecnicoController::class, 'guardarPresupuesto'])->whereNumber('sseId');
        Route::post('/{sseId}/presupuesto/enviar', [OrdenTecnicoController::class, 'enviarPresupuesto'])->whereNumber('sseId');
		
		Route::post('/{sseId}/reparacion/iniciar', [OrdenTecnicoController::class, 'iniciarReparacion'])->whereNumber('sseId');
        Route::put('/{sseId}/repuestos', [OrdenTecnicoController::class, 'guardarRepuestos'])->whereNumber('sseId');
        Route::post('/{sseId}/finalizar', [OrdenTecnicoController::class, 'finalizar'])->whereNumber('sseId');
        Route::post('/{sseId}/presupuesto/complementario', [OrdenTecnicoController::class, 'abrirComplementario'])->whereNumber('sseId');
        Route::delete('/{sseId}/presupuesto/complementario', [OrdenTecnicoController::class, 'descartarComplementario'])->whereNumber('sseId');
    
        Route::get('/{sseId}/diagnostico-preliminar', [DiagnosticoPreliminarController::class, 'tecnico'])->whereNumber('sseId');
    });

// Servicio técnico - Validación de presupuestos (control de precios)
Route::prefix('admin/servicios/presupuestos')
    ->middleware(['auth:api', 'permiso:servicios.presupuestos.ver'])
    ->group(function () {
        Route::get('/', [PresupuestoValidacionController::class, 'index']);
        Route::get('/{id}', [PresupuestoValidacionController::class, 'show'])->whereNumber('id');
        Route::post('/{id}/validar', [PresupuestoValidacionController::class, 'validar'])->whereNumber('id');
        Route::post('/{id}/devolver', [PresupuestoValidacionController::class, 'devolver'])->whereNumber('id');
    });
	
	// Servicio técnico - Panel general (A3), entrega (A5) y constancias
Route::prefix('admin/servicios/panel')->middleware('auth:api')->group(function () {
    Route::get('/{id}/constancia/{tipo}', [PanelServicioController::class, 'constancia'])
        ->whereNumber('id')->whereIn('tipo', ['recepcion', 'retiro'])
        ->middleware('permiso:servicios.solicitudes.ver,servicios.panel.ver');

    // Paso 2: aprobación asistida, contacto y PDF del presupuesto (desde A3 y desde el detalle de A1)
    Route::middleware('permiso:servicios.solicitudes.ver,servicios.panel.ver')->group(function () {
        Route::post('/{id}/presupuesto/respuesta', [PanelServicioController::class, 'responderPresupuesto'])->whereNumber('id');
        Route::post('/{id}/contacto', [PanelServicioController::class, 'registrarContacto'])->whereNumber('id');
        Route::get('/{id}/presupuesto/pdf', [PanelServicioController::class, 'pdfPresupuesto'])->whereNumber('id');
    });

    Route::middleware('permiso:servicios.panel.ver')->group(function () {
        Route::get('/resumen', [PanelServicioController::class, 'resumen']);
        Route::get('/config', [PanelServicioController::class, 'config']);
        Route::get('/', [PanelServicioController::class, 'index']);
        Route::post('/{id}/reasignar', [PanelServicioController::class, 'reasignar'])->whereNumber('id');
        Route::get('/{id}/entrega', [PanelServicioController::class, 'entregaConfig'])->whereNumber('id');
        Route::post('/{id}/entrega', [PanelServicioController::class, 'entregar'])->whereNumber('id');
    });
});

// Servicio técnico - Cobro del servicio (bandeja, verificación web y cobro en mostrador)
Route::prefix('admin/servicios/cobros')->middleware('auth:api')->group(function () {
    // Literales antes de {id}
    Route::get('/config', [CobroServicioController::class, 'config'])
        ->middleware('permiso:servicios.cobros.ver,servicios.panel.ver,servicios.solicitudes.ver');
    Route::get('/', [CobroServicioController::class, 'index'])
        ->middleware('permiso:servicios.cobros.ver');

    // Sección "Pagos" del detalle (A1/A3) y del modal A5
    Route::middleware('permiso:servicios.cobros.ver,servicios.panel.ver,servicios.solicitudes.ver')->group(function () {
        Route::get('/{id}', [CobroServicioController::class, 'show'])->whereNumber('id');
        Route::get('/{id}/pagos/{pago}/comprobante', [CobroServicioController::class, 'comprobante'])
            ->whereNumber('id')->whereNumber('pago');
    });

    // Verificación de pagos web: mismo permiso que en pedidos (decisión 14-b)
    Route::middleware('permiso:pedidos.pago.verificar')->group(function () {
        Route::post('/{id}/pagos/{pago}/confirmar', [CobroServicioController::class, 'confirmar'])->whereNumber('id')->whereNumber('pago');
        Route::post('/{id}/pagos/{pago}/rechazar', [CobroServicioController::class, 'rechazar'])->whereNumber('id')->whereNumber('pago');
    });

    // Cobro presencial: sin este permiso no se registran cobros (decisión 12-a)
    Route::middleware('permiso:servicios.cobros.confirmar')->group(function () {
        Route::post('/{id}/cobros', [CobroServicioController::class, 'registrarCobro'])->whereNumber('id');
        Route::post('/{id}/pagos/{pago}/cobrar-al-retirar', [CobroServicioController::class, 'cobrarAlRetirar'])
            ->whereNumber('id')->whereNumber('pago');
        // A5: además exige el acceso al panel (dos middleware = se exigen los DOS permisos)
        Route::post('/{id}/cobrar-entregar', [CobroServicioController::class, 'cobrarYEntregar'])
            ->whereNumber('id')->middleware('permiso:servicios.panel.ver');
    });

    // Facturación del servicio: consulta (los mismos permisos que la sección "Pagos")
    Route::middleware('permiso:servicios.cobros.ver,servicios.panel.ver,servicios.solicitudes.ver')->group(function () {
        Route::get('/{id}/facturacion', [FacturaServicioController::class, 'datosFiscales'])->whereNumber('id');
        Route::get('/{id}/factura', [FacturaServicioController::class, 'show'])->whereNumber('id');
        Route::get('/{id}/factura/kude', [FacturaServicioController::class, 'kude'])->whereNumber('id');
        Route::get('/{id}/factura/xml', [FacturaServicioController::class, 'xml'])->whereNumber('id');
    });

    // Datos fiscales: quien cobra en mostrador o quien verifica pagos web (con coma = cualquiera)
    Route::put('/{id}/facturacion', [FacturaServicioController::class, 'guardarDatosFiscales'])
        ->whereNumber('id')->middleware('permiso:servicios.cobros.confirmar,pedidos.pago.verificar');

    // Acciones sobre la factura: mismo criterio que facturas-venta
    Route::middleware('permiso:servicios.cobros.confirmar,facturas-venta.ver')->group(function () {
        Route::post('/{id}/factura/reintentar', [FacturaServicioController::class, 'reintentar'])
            ->whereNumber('id')->middleware('throttle:10,1');
        Route::post('/{id}/factura/reenviar', [FacturaServicioController::class, 'reenviar'])
            ->whereNumber('id')->middleware('throttle:5,1');
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

Route::prefix('admin/perfil')->middleware('auth:api')->group(function () {
    Route::get('/', [PerfilAdminController::class, 'show']);
    Route::post('/', [PerfilAdminController::class, 'update']);
    Route::patch('/password', [PerfilAdminController::class, 'actualizarPassword']);
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

    
    Route::get('/dato-fiscal/verificar-propio', [DatoFiscalClienteController::class, 'verificarPropio']);
    Route::get('/dato-fiscal', [DatoFiscalClienteController::class, 'index']);
    Route::post('/dato-fiscal', [DatoFiscalClienteController::class, 'store']);
    Route::put('/dato-fiscal/{id}', [DatoFiscalClienteController::class, 'update']);
    Route::delete('/dato-fiscal/{id}', [DatoFiscalClienteController::class, 'destroy']);

});

// Bandeja de pedidos del Panel Admin (solo lectura en esta etapa).
// Orden obligatorio: literales antes de cualquier {id}.

Route::prefix('admin/pedidos')->middleware('auth:api')->group(function () {
    // Literales SIEMPRE antes de {pedido}
    Route::get('/config',          [PedidoController::class, 'config'])
        ->middleware('permiso:pedidos.ver');
    Route::get('/exportar',        [PedidoController::class, 'exportar'])
        ->middleware('permiso:pedidos.exportar');
    Route::get('/gestion/config',  [PedidoGestionController::class, 'config'])
        ->middleware('permiso:pedidos.ver');

    Route::get('/',                [PedidoController::class, 'index'])
        ->middleware('permiso:pedidos.ver');
    Route::get('/{pedido}',        [PedidoGestionController::class, 'show'])
        ->middleware('permiso:pedidos.ver');

    // Confirmar un pago mueve dinero y stock: permiso propio.
    Route::post('/{pedido}/pagos/{pago}/confirmar', [PedidoGestionController::class, 'confirmarPago'])
        ->middleware('permiso:pedidos.pago.verificar');
    Route::post('/{pedido}/pagos/{pago}/rechazar',  [PedidoGestionController::class, 'rechazarPago'])
        ->middleware('permiso:pedidos.pago.verificar');

    Route::patch('/{pedido}/estado', [PedidoGestionController::class, 'cambiarEstado'])
        ->middleware('permiso:pedidos.gestionar');

    Route::post('/{pedido}/factura/reenviar', [PedidoGestionController::class, 'reenviarFactura'])
        ->middleware(['permiso:pedidos.gestionar', 'throttle:5,1']);

    Route::get('/{pedido}/factura/kude', [PedidoGestionController::class, 'kudeFactura'])
        ->middleware('permiso:pedidos.gestionar');

    Route::get('/{pedido}/pagos/{pago}/comprobante', [PedidoGestionController::class, 'comprobante'])
    ->middleware('permiso:pedidos.ver');
});


// Facturas de Venta del Panel Admin. Sin store(): la factura la emite
// el backend al cobrar o entregar el pedido, nunca el administrador.
Route::prefix('admin/facturas-venta')->middleware('auth:api')->group(function () {
    Route::get('/config', [VentaController::class, 'config'])
        ->middleware('permiso:facturas-venta.ver');

    Route::get('/', [VentaController::class, 'index'])
        ->middleware('permiso:facturas-venta.ver');

    Route::get('/{id}', [VentaController::class, 'show'])
        ->middleware('permiso:facturas-venta.ver');

    Route::patch('/{id}/anular', [VentaController::class, 'anular'])
        ->middleware('permiso:facturas-venta.ver');

        Route::get('/{id}/fe/kude', [VentaController::class, 'kude'])
        ->middleware('permiso:facturas-venta.ver');

    Route::get('/{id}/fe/xml', [VentaController::class, 'xml'])
        ->middleware('permiso:facturas-venta.ver');

    Route::post('/{id}/fe/reintentar', [VentaController::class, 'reintentarFe'])
        ->middleware(['permiso:facturas-venta.ver', 'throttle:10,1']);
});

/*
|--------------------------------------------------------------------------
| Módulo de Métricas y Reportes
|--------------------------------------------------------------------------
| Grupo propio (no dentro del admin con 'permiso:dashboard.ver') porque cada
| bloque exige su permiso específico. 'config' alimenta los combos de
| filtros y basta con tener acceso a cualquier reporte.
*/
Route::prefix('admin')->middleware('auth:api')->group(function () {

    Route::get('reportes/config', [ReporteConfigController::class, 'config'])
        ->middleware('permiso:metricas.dashboard.ver,reportes.ventas.ver,reportes.compras.ver,'
            . 'reportes.inventario.ver,reportes.servicio.ver,reportes.clientes.ver,'
            . 'reportes.promociones.ver,reportes.chatbot.ver,reportes.comportamiento.ver');

    // --- Paso 3: métricas del dashboard ---
    Route::prefix('metricas/dashboard')
        ->middleware('permiso:metricas.dashboard.ver')
        ->controller(DashboardController::class)
        ->group(function () {
            Route::get('resumen', 'resumen');
            Route::get('ventas-compras', 'ventasCompras');
            Route::get('distribucion', 'distribucion');
            Route::get('tablas', 'tablas');
        });
        
    // --- Reportes ---
    Route::prefix('reportes/ventas')
        ->middleware('permiso:reportes.ventas.ver')
        ->controller(ReporteVentasController::class)
        ->group(function () {
            Route::get('periodo', 'periodo');
            Route::get('productos', 'productos');
            Route::get('agrupado', 'agrupado');
            Route::get('metodos-pago', 'metodosPago');
            Route::get('ubicacion', 'ubicacion');
			
			Route::get('{vista}/exportar', [ReporteExportacionController::class, 'ventas'])
                ->whereIn('vista', ['periodo', 'productos', 'agrupado', 'metodos-pago', 'ubicacion']);
        });

    Route::prefix('reportes/inventario')
        ->middleware('permiso:reportes.inventario.ver')
        ->controller(ReporteInventarioController::class)
        ->group(function () {
            Route::get('valorizado', 'valorizado');
            Route::get('critico', 'critico');
            Route::get('sin-movimiento', 'sinMovimiento');
            Route::get('margen', 'margen');
			
			Route::get('{vista}/exportar', [ReporteExportacionController::class, 'inventario'])
                ->whereIn('vista', ['valorizado', 'critico', 'sin-movimiento', 'margen']);
        });

    // --- Reporte de compras ---
    Route::prefix('reportes/compras')
        ->middleware('permiso:reportes.compras.ver')
        ->group(function () {
            Route::get('proveedores', [ReporteComprasController::class, 'proveedores']);
            Route::get('cxp-vencidas', [ReporteComprasController::class, 'vencidas']);
            Route::get('cxp-por-vencer', [ReporteComprasController::class, 'porVencer']);

            Route::get('{vista}/exportar', [ReporteExportacionController::class, 'compras'])
                ->where('vista', 'proveedores|cxp-vencidas|cxp-por-vencer');
        });
    
    // --- Reporte de servicio técnico ---
    Route::prefix('reportes/servicio')
        ->middleware('permiso:reportes.servicio.ver')
        ->group(function () {
            Route::get('estados', [ReporteServicioController::class, 'estados']);
            Route::get('tecnicos', [ReporteServicioController::class, 'tecnicos']);
            Route::get('tiempos', [ReporteServicioController::class, 'tiempos']);
            Route::get('fallas', [ReporteServicioController::class, 'fallas']);
            Route::get('presupuestos', [ReporteServicioController::class, 'presupuestos']);

            Route::get('{vista}/exportar', [ReporteExportacionController::class, 'servicio'])
                ->where('vista', 'estados|tecnicos|tiempos|fallas|presupuestos');
            
            Route::get('diagnostico-ia', [ReporteServicioController::class, 'diagnosticoIa']);
             Route::get('clasificacion', [ReporteServicioController::class, 'clasificacion']);   // Punto 7
        });

   // --- Reporte de clientes ---
    Route::prefix('reportes/clientes')
        ->middleware('permiso:reportes.clientes.ver')
        ->group(function () {
            Route::get('top', [ReporteClientesController::class, 'top']);
            Route::get('nuevos', [ReporteClientesController::class, 'nuevos']);
            Route::get('inactivos', [ReporteClientesController::class, 'inactivos']);

            Route::get('{vista}/exportar', [ReporteExportacionController::class, 'clientes'])
                ->where('vista', 'top|nuevos|inactivos');
        });

    // --- Reporte de promociones ---
    Route::prefix('reportes/promociones')
        ->middleware('permiso:reportes.promociones.ver')
        ->group(function () {
            Route::get('cupones', [ReportePromocionesController::class, 'cupones']);
            Route::get('campanas', [ReportePromocionesController::class, 'campanas']);

            Route::get('{vista}/exportar', [ReporteExportacionController::class, 'promociones'])
                ->where('vista', 'cupones|campanas');
        });

    // --- Análisis de comportamiento de clientes ---
    Route::prefix('reportes/comportamiento')
        ->middleware('permiso:reportes.comportamiento.ver')
        ->controller(ReporteComportamientoController::class)
        ->group(function () {
            Route::get('busquedas', 'busquedas');
            Route::get('sin-resultados', 'sinResultados');
            Route::get('productos-buscados', 'productosBuscados');
            Route::get('franja-horaria', 'franjaHoraria');
            Route::get('comprados-juntos', 'compradosJuntos');
            Route::get('recompra', 'recompra');
            Route::get('modalidades', 'modalidades');
            Route::get('embudo', 'embudo');
            Route::get('carritos-abandonados', 'carritosAbandonados');
            Route::get('segmentos', 'segmentos');
            Route::get('clientes', 'buscarClientes')->middleware('throttle:60,1');
            Route::get('clientes/{cliente}', 'perfil')->whereNumber('cliente');
            Route::post('insights', 'generarInsights')->middleware('throttle:10,1');
        });
        
     // --- Reporte del asistente virtual ---
    Route::prefix('reportes/chatbot')
        ->middleware('permiso:reportes.chatbot.ver')
        ->group(function () {
            Route::get('periodo', [ReporteChatbotController::class, 'periodo']);
            Route::get('temas', [ReporteChatbotController::class, 'temas']);
            Route::get('preguntas', [ReporteChatbotController::class, 'preguntas']);
            Route::get('sin-respuesta', [ReporteChatbotController::class, 'sinRespuesta']);

            Route::get('{vista}/exportar', [ReporteExportacionController::class, 'chatbot'])
                ->where('vista', 'periodo|temas|preguntas|sin-respuesta');
        });

});


Route::get('publicaciones', [PublicacionWebController::class, 'index']);

Route::apiResource('categorias', categorieController::class);
Route::post('categorias/{id}/imagen', [categorieController::class, 'subirImagen']);
Route::delete('categorias/{id}/imagen', [categorieController::class, 'eliminarImagen']);


Route::get('marcas/select', [MarcaController::class, 'select']);
Route::patch('marcas/{id}/estado', [MarcaController::class, 'toggleEstado']);
Route::apiResource('marcas', MarcaController::class);