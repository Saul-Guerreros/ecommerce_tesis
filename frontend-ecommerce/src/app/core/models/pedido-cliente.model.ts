import {
  EntregaResumenCard, FacturacionResumenCard, ProductoResumenCardItem,
} from './resumen-compra.model';

/**
 * "Mis Pedidos" — lado cliente.
 *
 * Tipos separados de checkout.model.ts a propósito: el checkout modela un
 * pedido que se está armando (borrador, pago en curso); éste modela un
 * pedido que ya existe y se está consultando. Son vistas distintas del
 * mismo recurso, no el mismo objeto.
 *
 * 'entrega', 'facturacion' y 'productos' del detalle SÍ son los mismos
 * tipos que usa el Paso 3 del checkout (resumen-compra.model.ts): el
 * backend normaliza ambas respuestas a la misma forma para que las
 * tarjetas de resumen se vean "tal cual" en las dos pantallas.
 */

export type EstadoPedidoCodigo =
  | 'PENDIENTE' | 'EN_PREPARACION' | 'LISTO' | 'ENTREGADO' | 'CANCELADO';

export type EstadoPagoCodigo =
  | 'PENDIENTE_PAGO' | 'PENDIENTE' | 'CONFIRMADO' | 'RECHAZADO' | 'PARCIAL';

export type FiltroPedidos =
  | 'todos' | 'pendiente_pago' | 'en_proceso' | 'entregados' | 'cancelados';

export interface EstadoEtiquetado {
  codigo: string | null;
  descripcion: string | null;
}

// ---------- Listado ----------

export interface PedidoListaItem {
  ped_numero: string;
  ped_total: number;
  ped_creado: string;
  entrega: { codigo: string | null; etiqueta: string };
  estado_pedido: EstadoEtiquetado;
  estado_pago: EstadoEtiquetado;
  /** true -> mostrar el botón "Pagar ahora". Lo decide el backend. */
  pagable: boolean;
  ped_vence: string | null;
}

export interface PedidosPaginados {
  data: PedidoListaItem[];
  meta: { current_page: number; last_page: number; total: number };
}

// ---------- Detalle ----------

export interface PagoDetalle {
  orden: number;
  metodo: string | null;
  monto: number;
  estado: string | null;
  estado_descri: string | null;
  tiene_comprobante: boolean;
  comprobante_url: string | null;
}

export interface PedidoDetalle {
  ped_numero: string;
  ped_creado: string;
  ped_vence: string | null;
  pagable: boolean;
  estado_pedido: EstadoEtiquetado;
  estado_pago: EstadoEtiquetado;
  entrega: EntregaResumenCard | null;
  productos: ProductoResumenCardItem[];
  totales: { subtotal: number; impuestos: number; costo_envio: number; total: number };
  facturacion: FacturacionResumenCard | null;
  pagos: PagoDetalle[];
}

// ---------- Aviso de pedidos pendientes (punto rojo + tarjeta + contador) ----------

/**
 * Una fila del aviso. 'producto' es sólo el primero del pedido —
 * 'productos_adicionales' evita mentir por omisión cuando hay más de
 * uno ("+2" junto al nombre, en vez de mostrar sólo el primero sin avisar
 * que hay más).
 */
export interface PedidoPendienteItem {
  ped_numero: string;
  ped_total: number;
  ped_vence: string;
  producto: { imagen: string | null; nombre: string; cantidad: number } | null;
  productos_adicionales: number;
}