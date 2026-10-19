/**
 * Asocia una url del admin con el permiso necesario para verla.
 * Lo usan el sidebar (default-layout) y rutaPermisoGuard (auth/guards).
 *
 * Mantener en sincronía con App\Support\OpcionesAdmin (backend): esa clase es
 * el catálogo que muestra la pantalla Permisos. Las urls que no aparecen aquí
 * se muestran siempre (perfil, páginas de tema/demo, etc.).
 */
export const PERMISO_POR_RUTA: Record<string, string> = {
  '/publicaciones': 'publicaciones.ver',
  '/categories': 'categorias.ver',
  '/atributos/list': 'atributos.ver',
  '/plantillas-ficha': 'plantillas-ficha.ver',
  '/brands': 'marcas.ver',
  '/sliders/list': 'sliders.ver',
  '/cupones/list': 'cupones.ver',
  '/discounts/list': 'descuentos.ver',
  '/chatbot/informacion': 'chatbot.gestionar',
  '/pedidos': 'pedidos.ver',
  '/facturas-venta': 'facturas-venta.ver',
  '/proveedores': 'proveedores.ver',
  '/tipos-proveedor': 'tipos-proveedor.ver',
  '/facturas-compra': 'facturas-compra.ver',
  '/cuentas-por-pagar': 'cuentas-por-pagar.ver',
  '/libro-compra': 'libro-compra.ver',
  '/inventario/productos': 'productos.ver',
  '/productos': 'productos.ver',
  '/unidades-medida': 'unidades-medida.ver',
  '/configuracion-precio': 'configuracion-margen.ver',
  '/precios': 'precios.ver',
  '/inventario/ajustes': 'ajustes-inventario.ver',
  '/inventario/tipos-ajuste': 'tipos-ajuste.ver',
  '/inventario/kardex': 'kardex.ver',
  '/inventario/stock-actual': 'stock.ver',
  '/gestion-cuotas': 'cuotas.ver',
  '/configuracion-tienda': 'alertas-stock.ver',
  '/configuracion-pago': 'metodos-pago.ver',
  '/configuracion-envio': 'configuracion-envio.ver',
  '/tarifas-envio': 'tarifas-envio.ver',
  '/clientes': 'clientes.ver',
  '/tipos-cliente': 'tipos-cliente.ver',
  '/funcionarios': 'funcionarios.ver',
  '/cargos': 'cargos.ver',
  '/tipos-documento': 'tipos-documento.ver',
  '/usuarios/list': 'usuarios.ver',
  '/roles': 'roles.gestionar',
  '/permisos': 'permisos.gestionar',
  '/seguridad': 'auditoria.ver',
  '/empresas': 'empresas.ver',
  '/sucursales': 'sucursales.ver',
  '/depositos': 'depositos.ver',
  '/timbrados': 'timbrados.ver',
  '/tipos-comprobante': 'tipos-comprobante.ver',
  '/tipos-iva': 'tipos-iva.ver',
  '/departamentos': 'departamentos.ver',
  '/ciudades': 'ciudades.ver',
  '/categories/new': 'categorias.gestionar',
  '/brands/new': 'marcas.gestionar',
  
  '/servicios/solicitudes': 'servicios.solicitudes.ver',
  '/servicios/panel': 'servicios.panel.ver',
  '/servicios/mis-ordenes': 'servicios.tecnico.ver',
  '/servicios/tipos-dispositivo': 'tipos-dispositivo.ver',
  '/servicios/fallas': 'fallas.ver',
  '/servicios/trabajos': 'trabajos.ver',
  '/servicios/presupuestos': 'servicios.presupuestos.ver',
};

// Claves más largas primero: '/categories/new' gana sobre '/categories'.
const CLAVES = Object.keys(PERMISO_POR_RUTA).sort((a, b) => b.length - a.length);

/** Permiso que exige una url (coincidencia exacta o por prefijo de ruta), o null si es libre. */
export function permisoParaUrl(url: string): string | null {
  const limpia = url.split('?')[0].split('#')[0];
  const clave = CLAVES.find((k) => limpia === k || limpia.startsWith(k + '/'));
  return clave ? PERMISO_POR_RUTA[clave] : null;
}
