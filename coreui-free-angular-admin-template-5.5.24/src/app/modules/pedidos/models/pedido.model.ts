/**
 * Contratos del listado de pedidos. Reflejan exactamente lo que devuelve
 * PedidoListaResource: si el backend cambia, TypeScript avisa en compilación.
 */

export interface ClienteResumen {
  nombre: string;
  cedula: string | null;
}

export interface EntregaResumen {
  codigo: string | null;      // 'RETIRO' | 'ENVIO'
  etiqueta: string;           // 'Retiro en Sucursal' | 'Envío a domicilio'
}

export interface MetodoPagoResumen {
  etiqueta: string;           // 'Transferencia Bancaria' | 'Dividido 2 pagos'
  dividido: boolean;
  cantidad_pagos: number;
}

export interface EstadoResumen {
  codigo: string | null;
  descripcion: string | null;
  detalle?: string | null;    // '1/2 verificados', '1 comprob.', etc.
}

/** Plazo de retiro en sucursal. Sólo tiene valor con el pedido en LISTO y entrega RETIRO. */
export interface RetiroResumen {
  vence: string;
  /**
   * El barrido no cancela automáticamente un retiro vencido con pago
   * confirmado: lo deja así, en rojo, para que el admin lo resuelva a
   * mano (extender el plazo, mandarlo a domicilio, o devolver el dinero).
   */
  vencido: boolean;
}

export interface PedidoLista {
  ped_id: number;
  numero: string | null;
  fecha: string | null;             // 'DD/MM HH:mm'
  fecha_completa: string | null;    // 'DD/MM/YYYY HH:mm'
  cliente: ClienteResumen;
  entrega: EntregaResumen;
  total: number;
  metodo_pago: MetodoPagoResumen;
  verificacion_pago: EstadoResumen;
  estado_pedido: EstadoResumen;
  retiro: RetiroResumen | null;
}

/** Parámetros que acepta GET /admin/pedidos y /admin/pedidos/exportar. */
export interface PedidoFiltros {
  num?: string;
  ci?: string;
  cliente?: string;
  estado_pedido?: number | null;
  estado_pago?: number | null;
  tipo_entrega?: number | null;
  metodo_pago?: number | null;
  desde?: string;      // YYYY-MM-DD
  hasta?: string;      // YYYY-MM-DD
  retiro_vencido?: boolean;
  page?: number;
  per_page?: number;
}

export interface PedidosPaginados {
  data: PedidoLista[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    desde: number;
    hasta: number;
    pagos_pendientes: number;
    retiros_vencidos: number;
  };
}

// ---------- Catálogos de los combos ----------

export interface EstadoPedidoOption {
  est_id: number;
  est_codigo: string;
  est_descri: string;
}

export interface EstadoPagoOption {
  epa_id: number;
  epa_codigo: string;
  epa_descri: string;
}

export interface TipoEntregaOption {
  ten_id: number;
  ten_codigo: string;
  ten_descri: string;
}

export interface MetodoPagoOption {
  mpa_id: number;
  mpa_codigo: string;
  mpa_descri: string;
}

export interface PedidosConfig {
  estados_pedido: EstadoPedidoOption[];
  estados_pago: EstadoPagoOption[];
  tipos_entrega: TipoEntregaOption[];
  metodos_pago: MetodoPagoOption[];
}

/** Campo de búsqueda activo. Solo uno puede tener contenido a la vez. */
export type CampoBusqueda = 'num' | 'ci' | 'cliente';