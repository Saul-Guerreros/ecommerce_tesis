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
  | 'PENDIENTE_PEDIDO' | 'EN_PREPARACION' | 'LISTO' | 'ENTREGADO' | 'CANCELADO';

export type EstadoPagoCodigo =
  | 'PENDIENTE_PAGO' | 'PAGO_VERIFICAR' | 'CONFIRMADO' | 'RECHAZADO' | 'PARCIAL';

export type FiltroPedidos =
  | 'todos' | 'pendiente_pago' | 'en_proceso' | 'entregados' | 'cancelados';

export interface EstadoEtiquetado {
  codigo: string | null;
  descripcion: string | null;
}

/**
 * Plazo para entregar el cheque físico en la sucursal. null si el pedido
 * no tiene cheques o si ya se recibieron todos.
 */
export interface PlazoCheque {
  vence: string;
  horas_plazo: number;
  /** Cheques todavía sin entregar (pago dividido). */
  cantidad: number;
}

/** Eje "Cheque" de la cabecera. null si el pedido no tiene cheques. */
export interface EstadoChequePedido {
  codigo: string;
  descripcion: string;
  total: number;
  recibidos: number;
}

// ---------- Listado ----------

export interface PedidoListaItem {
  ped_numero: string;
  ped_total: number;
  ped_creado: string;
  entrega: { codigo: string | null; etiqueta: string };
  estado_pedido: EstadoEtiquetado;
  estado_pago: EstadoEtiquetado;
  estado_cheque: EstadoChequePedido | null;
  
  /** true -> mostrar el botón "Pagar ahora". Lo decide el backend. */
  pagable: boolean;
  ped_vence: string | null;
  /** Fecha límite de retiro en sucursal. Sólo tiene valor con el pedido en LISTO y entrega RETIRO. */
  ped_vencret: string | null;

  /** Tercer reloj: plazo de entrega del cheque. Independiente de los dos anteriores. */
  cheque: PlazoCheque | null;
}

export interface PedidosPaginados {
  data: PedidoListaItem[];
  meta: { current_page: number; last_page: number; total: number };
}

// ---------- Detalle ----------

/** Por qué el admin rechazó este pago. null si no está rechazado. */
export interface MotivoRechazoPago {
  motivo: string | null;
  detalle: string | null;
  fecha: string | null;
}

export interface PagoDetalle {
  ppa_id: number;
  orden: number;
  metodo: string | null;
  monto: number;
  estado: string | null;
  estado_descri: string | null;
  tiene_comprobante: boolean;
  comprobante_url: string | null;
  motivo_rechazo: MotivoRechazoPago | null;
  /** true -> mostrar el formulario de comprobante nuevo. Lo decide el backend. */
  reemplazable: boolean;
    /** Datos del cheque declarado. null en los demás métodos. */
  cheque: {
    tipo: 'VISTA' | 'DIFERIDO';
    tipo_descri: string;
    banco: string | null;
    nro: string | null;
    fecha_cobro: string | null;
    estado: string;
    estado_label: string;
  } | null;
}

export interface PedidoDetalle {
  ped_numero: string;
  ped_creado: string;
  ped_vence: string | null;
  pagable: boolean;
  /** Intentos que le quedan antes de que el pedido se cancele solo. */
  rechazos_restantes: number;
  /** Fecha límite de retiro en sucursal. Sólo tiene valor con el pedido en LISTO y entrega RETIRO. */
  ped_vencret: string | null;
  /** Tercer reloj: plazo de entrega del cheque. Independiente de los dos anteriores. */
  cheque: PlazoCheque | null;
  estado_pedido: EstadoEtiquetado;
  estado_pago: EstadoEtiquetado;
  estado_cheque: EstadoChequePedido | null;
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
  /** 'PENDIENTE_PAGO' (nunca declaró pago) o 'RECHAZADO' (comprobante a corregir). */
  estado_pago: 'PENDIENTE_PAGO' | 'RECHAZADO';
  producto: { imagen: string | null; nombre: string; cantidad: number } | null;
  productos_adicionales: number;
}

