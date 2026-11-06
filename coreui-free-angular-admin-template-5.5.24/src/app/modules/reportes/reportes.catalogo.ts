/**
 * Catálogo único de reportes: lo usa la página de inicio para mostrar
 * las tarjetas según los permisos del usuario.
 *
 * Permisos sincronizados con App\Support\OpcionesAdmin (backend) y
 * _nav-permisos.ts. "disponible" pasa a true a medida que se implementa
 * cada reporte (Pasos 6 y 8).
 */
export interface ItemCatalogoReporte {
  clave: string;
  titulo: string;
  descripcion: string;
  ruta: string;
  permiso: string;
  icono: string;
  contenidos: string[];
  disponible: boolean;
}

export const CATALOGO_REPORTES: ItemCatalogoReporte[] = [
  {
    clave: 'ventas', titulo: 'Ventas', icono: 'cilChart', ruta: '/reportes/ventas',
    permiso: 'reportes.ventas.ver', disponible: true,
    descripcion: 'Ventas concretadas (pedidos pagados) y su composición.',
    contenidos: ['Por período', 'Por producto', 'Por categoría y marca', 'Por método de pago', 'Por ciudad'],
  },
  {
    clave: 'inventario', titulo: 'Inventario', icono: 'cilStorage', ruta: '/reportes/inventario',
    permiso: 'reportes.inventario.ver', disponible: true,
    descripcion: 'Valorización, stock crítico, rotación y margen.',
    contenidos: ['Stock valorizado por depósito', 'Stock crítico', 'Productos sin movimiento', 'Margen por producto'],
  },
  {
    clave: 'compras', titulo: 'Compras', icono: 'cilCart', ruta: '/reportes/compras',
    permiso: 'reportes.compras.ver', disponible: true,
    descripcion: 'Compras confirmadas y obligaciones con proveedores.',
    contenidos: ['Por proveedor', 'Cuentas por pagar vencidas', 'Cuentas por pagar por vencer'],
  },
  {
    clave: 'servicio', titulo: 'Servicio técnico', icono: 'cilSettings', ruta: '/reportes/servicio',
    permiso: 'reportes.servicio.ver', disponible: true,
    descripcion: 'Seguimiento y rendimiento del servicio técnico.',
    contenidos: ['Órdenes por estado', 'Órdenes por técnico', 'Tiempo promedio de reparación', 'Fallas frecuentes', 'Presupuestos aprobados/rechazados'],
  },
  {
    clave: 'clientes', titulo: 'Clientes', icono: 'cilPeople', ruta: '/reportes/clientes',
    permiso: 'reportes.clientes.ver', disponible: true,
    descripcion: 'Comportamiento de compra de la cartera de clientes.',
    contenidos: ['Top clientes', 'Clientes nuevos por mes', 'Clientes inactivos'],
  },
  {
    clave: 'promociones', titulo: 'Promociones', icono: 'cilTags', ruta: '/reportes/promociones',
    permiso: 'reportes.promociones.ver', disponible: true,
    descripcion: 'Cupones y campañas configuradas (uso: trabajo futuro).',
    contenidos: ['Cupones vigentes y vencidos', 'Campañas de descuento'],
  },
  {
    clave: 'chatbot', titulo: 'Asistente virtual', icono: 'cilChatBubble', ruta: '/reportes/chatbot',
     permiso: 'reportes.chatbot.ver', disponible: true,
    descripcion: 'Uso y efectividad del chatbot con IA.',
    contenidos: ['Conversaciones por período', 'Temas más consultados', 'Consultas sin respuesta y derivadas'],
  },
  {
    clave: 'comportamiento', titulo: 'Comportamiento de clientes', icono: 'cilMagnifyingGlass', ruta: '/reportes/comportamiento',
    permiso: 'reportes.comportamiento.ver', disponible: true,
    descripcion: 'Qué buscan, cómo compran y qué prefieren los clientes, con recomendaciones de IA.',
    contenidos: ['Productos más buscados y demanda insatisfecha', 'Patrones de compra', 'Segmentos RFM', 'Perfil por cliente', 'Recomendaciones con IA'],
  },
];