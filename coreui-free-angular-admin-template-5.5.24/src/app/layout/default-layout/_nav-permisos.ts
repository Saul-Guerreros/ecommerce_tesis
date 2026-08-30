/**
 * Asocia una url del sidebar (_nav.ts) con el permiso del backend que hace
 * falta para verla (ver RolPermisoSeeder en el backend). Los items cuya url
 * no aparece aca se muestran siempre (paginas de tema/demo del template).
 *
 * Para agregar un modulo nuevo (por ejemplo una pantalla de "Usuarios"),
 * simplemente agrega su url y el permiso correspondiente aca.
 */
export const PERMISO_POR_RUTA: Record<string, string> = {
  '/categories': 'categorias.ver',
  '/categories/new': 'categorias.gestionar',
  '/brands': 'marcas.ver',
  '/brands/new': 'marcas.gestionar',
  '/atributos/list': 'atributos.ver',
  '/productos': 'productos.ver',
  '/sliders/list': 'sliders.ver',
  '/usuarios/list': 'usuarios.ver',
};
