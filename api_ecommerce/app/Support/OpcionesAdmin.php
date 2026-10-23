<?php

namespace App\Support;

/**
 * Catálogo de opciones del panel admin que se pueden asignar por rol
 * (pantalla Permisos). Cada opción = 1 permiso de acceso en la tabla `permiso`.
 *
 * Debe mantenerse en sincronía con el sidebar (_nav.ts) y con
 * layout/default-layout/_nav-permisos.ts del admin.
 * Al agregar una opción nueva: sumarla aquí y volver a correr OpcionesAdminPermisoSeeder.
 */
class OpcionesAdmin
{
    /** @return array<int, array{nombre:string, opciones:array<int, array>}> */
    public static function grupos(): array
    {
        return [
            [
                'nombre' => 'General',
                'opciones' => [
                    ['permiso' => 'dashboard.ver', 'nombre' => 'Acceso al panel (iniciar sesión)', 'urls' => [], 'fijo' => false],
                ],
            ],
            [
                'nombre' => 'Publicación Web',
                'opciones' => [
                    ['permiso' => 'publicaciones.ver', 'nombre' => 'Lista de publicaciones', 'urls' => ['/publicaciones'], 'fijo' => false],
                    ['permiso' => 'categorias.ver', 'nombre' => 'Categorías', 'urls' => ['/categories'], 'fijo' => false],
                    ['permiso' => 'atributos.ver', 'nombre' => 'Atributos', 'urls' => ['/atributos/list'], 'fijo' => false],
                    ['permiso' => 'plantillas-ficha.ver', 'nombre' => 'Plantillas de ficha técnica', 'urls' => ['/plantillas-ficha'], 'fijo' => false],
                    ['permiso' => 'marcas.ver', 'nombre' => 'Marcas', 'urls' => ['/brands'], 'fijo' => false],
                    ['permiso' => 'sliders.ver', 'nombre' => 'Sliders', 'urls' => ['/sliders/list'], 'fijo' => false],
                    ['permiso' => 'cupones.ver', 'nombre' => 'Cupones', 'urls' => ['/cupones/list'], 'fijo' => false],
                    ['permiso' => 'descuentos.ver', 'nombre' => 'Campañas de descuento', 'urls' => ['/discounts/list'], 'fijo' => false],
                    ['permiso' => 'chatbot.gestionar', 'nombre' => 'Asistente virtual', 'urls' => ['/chatbot/informacion'], 'fijo' => false],
                ],
            ],
            [
                'nombre' => 'Ventas',
                'opciones' => [
                    ['permiso' => 'pedidos.ver', 'nombre' => 'Pedidos', 'urls' => ['/pedidos'], 'fijo' => false],
                    ['permiso' => 'facturas-venta.ver', 'nombre' => 'Facturas de venta', 'urls' => ['/facturas-venta'], 'fijo' => false],
                ],
            ],
            [
                'nombre' => 'Compras',
                'opciones' => [
                    ['permiso' => 'proveedores.ver', 'nombre' => 'Proveedores', 'urls' => ['/proveedores'], 'fijo' => false],
                    ['permiso' => 'tipos-proveedor.ver', 'nombre' => 'Tipos de proveedor', 'urls' => ['/tipos-proveedor'], 'fijo' => false],
                    ['permiso' => 'facturas-compra.ver', 'nombre' => 'Facturas de compra', 'urls' => ['/facturas-compra'], 'fijo' => false],
                    ['permiso' => 'cuentas-por-pagar.ver', 'nombre' => 'Cuentas por pagar', 'urls' => ['/cuentas-por-pagar'], 'fijo' => false],
                    ['permiso' => 'libro-compra.ver', 'nombre' => 'Libro de compras', 'urls' => ['/libro-compra'], 'fijo' => false],
                ],
            ],
            [
                'nombre' => 'Inventario',
                'opciones' => [
                    ['permiso' => 'productos.ver', 'nombre' => 'Productos', 'urls' => ['/inventario/productos', '/productos'], 'fijo' => false],
                    ['permiso' => 'unidades-medida.ver', 'nombre' => 'Unidades de medida', 'urls' => ['/unidades-medida'], 'fijo' => false],
                    ['permiso' => 'configuracion-margen.ver', 'nombre' => 'Configuración de margen', 'urls' => ['/configuracion-precio'], 'fijo' => false],
                    ['permiso' => 'precios.ver', 'nombre' => 'Gestión de precios', 'urls' => ['/precios'], 'fijo' => false],
                    ['permiso' => 'ajustes-inventario.ver', 'nombre' => 'Ajustes de inventario', 'urls' => ['/inventario/ajustes'], 'fijo' => false],
                    ['permiso' => 'tipos-ajuste.ver', 'nombre' => 'Tipos de ajuste', 'urls' => ['/inventario/tipos-ajuste'], 'fijo' => false],
                    ['permiso' => 'kardex.ver', 'nombre' => 'Movimientos de stock (Kardex)', 'urls' => ['/inventario/kardex'], 'fijo' => false],
                    ['permiso' => 'stock.ver', 'nombre' => 'Stock actual', 'urls' => ['/inventario/stock-actual'], 'fijo' => false],
                ],
            ],
            [
                'nombre' => 'Ajustes de la Tienda',
                'opciones' => [
                    ['permiso' => 'cuotas.ver', 'nombre' => 'Gestión de cuotas', 'urls' => ['/gestion-cuotas'], 'fijo' => false],
                    ['permiso' => 'alertas-stock.ver', 'nombre' => 'Alertas de stock', 'urls' => ['/configuracion-tienda'], 'fijo' => false],
                    ['permiso' => 'metodos-pago.ver', 'nombre' => 'Métodos de pago', 'urls' => ['/configuracion-pago'], 'fijo' => false],
                    ['permiso' => 'configuracion-envio.ver', 'nombre' => 'Configuración de envío', 'urls' => ['/configuracion-envio'], 'fijo' => false],
                    ['permiso' => 'tarifas-envio.ver', 'nombre' => 'Tarifas de envío', 'urls' => ['/tarifas-envio'], 'fijo' => false],
                ],
            ],
            [
                'nombre' => 'Personas',
                'opciones' => [
                    ['permiso' => 'clientes.ver', 'nombre' => 'Clientes', 'urls' => ['/clientes'], 'fijo' => false],
                    ['permiso' => 'tipos-cliente.ver', 'nombre' => 'Tipos de cliente', 'urls' => ['/tipos-cliente'], 'fijo' => false],
                    ['permiso' => 'funcionarios.ver', 'nombre' => 'Funcionarios', 'urls' => ['/funcionarios'], 'fijo' => false],
                    ['permiso' => 'cargos.ver', 'nombre' => 'Cargos', 'urls' => ['/cargos'], 'fijo' => false],
                    ['permiso' => 'tipos-documento.ver', 'nombre' => 'Tipos de documento', 'urls' => ['/tipos-documento'], 'fijo' => false],
                    ['permiso' => 'usuarios.ver', 'nombre' => 'Usuarios del sistema', 'urls' => ['/usuarios/list'], 'fijo' => false],
                ],
            ],
            [
                'nombre' => 'Seguridad y Acceso',
                'opciones' => [
                    ['permiso' => 'roles.gestionar', 'nombre' => 'Roles', 'urls' => ['/roles'], 'fijo' => false],
                    ['permiso' => 'permisos.gestionar', 'nombre' => 'Permisos', 'urls' => ['/permisos'], 'fijo' => false],
                    ['permiso' => 'auditoria.ver', 'nombre' => 'Auditoría (4 pantallas)', 'urls' => ['/seguridad'], 'fijo' => false],
                ],
            ],
            [
                'nombre' => 'Configuración',
                'opciones' => [
                    ['permiso' => 'empresas.ver', 'nombre' => 'Datos de la empresa', 'urls' => ['/empresas'], 'fijo' => false],
                    ['permiso' => 'sucursales.ver', 'nombre' => 'Sucursales', 'urls' => ['/sucursales'], 'fijo' => false],
                    ['permiso' => 'depositos.ver', 'nombre' => 'Depósitos', 'urls' => ['/depositos'], 'fijo' => false],
                    ['permiso' => 'timbrados.ver', 'nombre' => 'Timbrados', 'urls' => ['/timbrados'], 'fijo' => false],
                    ['permiso' => 'tipos-comprobante.ver', 'nombre' => 'Tipos de comprobante', 'urls' => ['/tipos-comprobante'], 'fijo' => false],
                    ['permiso' => 'tipos-iva.ver', 'nombre' => 'Tipos de IVA', 'urls' => ['/tipos-iva'], 'fijo' => false],
                    ['permiso' => 'departamentos.ver', 'nombre' => 'Departamentos', 'urls' => ['/departamentos'], 'fijo' => false],
                    ['permiso' => 'ciudades.ver', 'nombre' => 'Ciudades', 'urls' => ['/ciudades'], 'fijo' => false],
                ],
            ],
			[
				'nombre' => 'Servicio Técnico',
				'opciones' => [
					['permiso' => 'servicios.solicitudes.ver', 'nombre' => 'Bandeja de solicitudes',     'urls' => ['/servicios/solicitudes'], 'fijo' => false],
					['permiso' => 'servicios.panel.ver',       'nombre' => 'Panel general de servicios', 'urls' => ['/servicios/panel'], 'fijo' => false],
					['permiso' => 'servicios.tecnico.ver',     'nombre' => 'Mis órdenes (técnico)',      'urls' => ['/servicios/mis-ordenes'], 'fijo' => false],
					['permiso' => 'tipos-dispositivo.ver',     'nombre' => 'Tipos de dispositivo',       'urls' => ['/servicios/tipos-dispositivo'], 'fijo' => false],
					['permiso' => 'fallas.ver',                'nombre' => 'Fallas',                     'urls' => ['/servicios/fallas'], 'fijo' => false],
					['permiso' => 'trabajos.ver',              'nombre' => 'Trabajos (mano de obra)',    'urls' => ['/servicios/trabajos'], 'fijo' => false],
				    ['permiso' => 'servicios.presupuestos.ver', 'nombre' => 'Validación de presupuestos', 'urls' => ['/servicios/presupuestos'], 'fijo' => false],
					['permiso' => 'servicios.cobros.ver',       'nombre' => 'Cobros de servicios',        'urls' => ['/servicios/cobros'], 'fijo' => false],
					// Acción sin pantalla propia: cobrar en el mostrador (anticipo, saldo, "Cobrar y entregar").
					['permiso' => 'servicios.cobros.confirmar', 'nombre' => 'Cobrar servicios en el mostrador', 'urls' => [], 'fijo' => false],
				],
			],
            [
                'nombre' => 'Métricas y Reportes',
                'opciones' => [
                    ['permiso' => 'metricas.dashboard.ver',   'nombre' => 'Métricas del dashboard',      'urls' => [],                         'fijo' => false],
                    ['permiso' => 'reportes.ventas.ver',      'nombre' => 'Reportes de ventas',          'urls' => ['/reportes/ventas'],       'fijo' => false],
                    ['permiso' => 'reportes.compras.ver',     'nombre' => 'Reportes de compras',         'urls' => ['/reportes/compras'],      'fijo' => false],
                    ['permiso' => 'reportes.inventario.ver',  'nombre' => 'Reportes de inventario',      'urls' => ['/reportes/inventario'],   'fijo' => false],
                    ['permiso' => 'reportes.servicio.ver',    'nombre' => 'Reportes de servicio técnico','urls' => ['/reportes/servicio'],     'fijo' => false],
                    ['permiso' => 'reportes.clientes.ver',    'nombre' => 'Reportes de clientes',        'urls' => ['/reportes/clientes'],     'fijo' => false],
                    ['permiso' => 'reportes.promociones.ver', 'nombre' => 'Reportes de promociones',     'urls' => ['/reportes/promociones'],  'fijo' => false],
                    ['permiso' => 'reportes.chatbot.ver',     'nombre' => 'Reportes del asistente virtual','urls' => ['/reportes/chatbot'],    'fijo' => false],
                ],
            ],
        ];
    }

    /** Lista plana de opciones. */
    public static function opciones(): array
    {
        return array_merge(...array_column(self::grupos(), 'opciones'));
    }

    /** Todos los códigos de permiso del catálogo. */
    public static function codigos(): array
    {
        return array_column(self::opciones(), 'permiso');
    }

    /** Códigos que se pueden marcar/desmarcar (excluye los fijos, como dashboard.ver). */
    public static function codigosEditables(): array
    {
        return array_column(array_filter(self::opciones(), fn ($o) => !$o['fijo']), 'permiso');
    }
}
