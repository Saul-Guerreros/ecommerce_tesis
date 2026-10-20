import { Direccion } from './direccion.model';
import { DatoFiscal } from './dato-fiscal.model';
import { CarritoItem } from './carrito.model';
import {
  EntregaResumenCard, FacturacionResumenCard, ProductoResumenCardItem,
} from './resumen-compra.model';

export type ModalidadEntrega = 'retiro' | 'envio';
export type TipoReceptor = 'titular' | 'tercero';

export interface SucursalRetiro {
  suc_id: number;
  suc_nombre: string;
  suc_direccion: string | null;
  suc_horario: string | null;
  suc_telef: string | null;
  ciudad?: string | null;
}

export interface TitularCheckout {
  rec_nrodoc: string | null;
  rec_nombre: string;
  rec_apelli: string;
  rec_telef: string | null;
}

export interface ConfigEnvio {
  modo: 'gratis' | 'fijo' | 'sucursal';
  costo: number;
  gratis_desde: number;
  subtotal: number;
  impuestos: number;
}

export interface EntregaCheckout {
  modalidad: ModalidadEntrega;
  ten_id: number | null;
  ten_codigo: string | null;
  receptor: TipoReceptor;

  suc_id: number | null;
  suc_nombre: string | null;
  suc_direccion: string | null;
  suc_horario: string | null;

  dir_id: number | null;
  departamento: string | null;
  ciudad: string | null;
  barrio: string | null;
  calle: string | null;
  nrocasa: string | null;
  referencia: string | null;

  rec_nrodoc: string;
  rec_nombre: string;
  rec_apelli: string;
  rec_telef: string | null;

  costo_envio: number;
}

export interface ConfigEntregaResponse {
  sucursales: SucursalRetiro[];
  direcciones: Direccion[];
  titular: TitularCheckout;
  carrito_vacio: boolean;
  envio: ConfigEnvio;
  // Alimenta la tarjeta "Productos en Carrito" del Paso 1.
  items: CarritoItem[];
  entrega: EntregaCheckout | null;
}

export interface GuardarEntregaRequest {
  modalidad: ModalidadEntrega;
  receptor: TipoReceptor;
  suc_id?: number | null;
  dir_id?: number | null;
  rec_nrodoc?: string | null;
  rec_nombre?: string | null;
  rec_apelli?: string | null;
  rec_telef?: string | null;
}

// ---------- Paso 2: Facturación + Confirmar Pedido ----------

export type TipoDocumentoFacturacion = 'CI' | 'RUC';
export type MedioRecepcion = 'CORREO' | 'WHATSAPP';

export interface TitularFacturacion {
  numero: string | null;
  nombre: string;
  apellido: string;
  telefono: string | null;
  correo: string;
}

export interface FacturacionCheckout {
  tipo_documento: TipoDocumentoFacturacion;
  dfi_id: number | null;
  pfa_nrodoc: string;
  pfa_raznom: string;
  pfa_telefono: string;
  pfa_correo: string;
  pfa_recibe: MedioRecepcion;
}

export interface ResumenCompra {
  subtotal: number;
  impuestos: number;
  costo_envio: number;
  total?: number;
}

/**
 * 'items' llega desde el rediseño del checkout: el Paso 2 es ahora el
 * último punto antes de comprometer stock, así que el cliente tiene que
 * poder revisar los productos acá. La pantalla de confirmación que los
 * mostraba antes ya no existe.
 */
export interface ConfigFacturacionResponse {
  titular: TitularFacturacion;
  datos_fiscales: DatoFiscal[];
  entrega: EntregaCheckout | null;
  items: CarritoItem[];
  resumen: ResumenCompra;
  facturacion: FacturacionCheckout | null;
}

export interface GuardarFacturacionRequest {
  tipo_documento: TipoDocumentoFacturacion;
  dfi_id?: number | null;
  pfa_raznom?: string | null;
  pfa_nrodoc?: string | null;
  pfa_telefono?: string | null;
  pfa_correo?: string | null;
  pfa_recibe: MedioRecepcion;
}

/**
 * Respuesta de [CONFIRMAR PEDIDO]. A partir de acá el pedido existe y
 * tiene el stock reservado: ped_vence es el plazo que tiene el cliente
 * para declarar su pago antes de que esa reserva se libere.
 */
export interface PedidoConfirmado {
  ped_id: number;
  ped_numero: string;
  ped_total: number;
  ped_creado: string;
  ped_vence: string;
  estado_pago: string;
}

// ---------- Paso 3: Pago de un pedido ya creado ----------

export type MetodoPagoCodigo = 'TRANSFERENCIA' | 'BILLETERA_MOVIL' | 'CONTRAENTREGA';

export interface MetodoPagoOpcion {
  mpa_id: number;
  mpa_codigo: MetodoPagoCodigo;
  mpa_descri: string;
  mpa_requcom: boolean;
}

export interface DatosTransferencia {
  banco: string | null;
  titular: string | null;
  cuenta: string | null;
}

export interface DatosBilletera {
  titular: string | null;
  numero: string | null;
  referencia: string | null;
}

/** Forma en la que el cliente decide cubrir el total del pedido. */
export type ModoPago = 'unico' | 'dividido';

/** Pedido que se está por pagar, tal como lo informa el backend. */
export interface PedidoAPagar {
  ped_numero: string;
  ped_total: number;
  ped_creado: string;
  ped_vence: string;
}

export interface ConfigPagoPedidoResponse {
  pedido: PedidoAPagar;
  metodos: MetodoPagoOpcion[];
  datos_transferencia: DatosTransferencia;
  datos_billetera: DatosBilletera;
  mensaje_efectivo: string | null;

  // Tarjetas de resumen del Paso 3 (Resumen de Compra, Productos en
  // Carrito, Datos de Entrega, Datos de Facturación). 'entrega' y
  // 'facturacion' ya vienen en la forma de resumen-compra.model.ts: no
  // hace falta adaptarlos.
  resumen: ResumenCompra;
  productos: ProductoResumenCardItem[];
  entrega: EntregaResumenCard | null;
  facturacion: FacturacionResumenCard | null;
}

/** Un bloque de pago tal como lo arma el formulario del Paso 3. */
export interface PagoDividRequest {
  mpa_codigo: MetodoPagoCodigo;
  monto: number;
  comprobante: File | null;
  /**
   * @deprecated Sin uso desde que el pago dejó de guardarse como borrador:
   * ahora se declara una sola vez y el archivo siempre viaja completo.
   * Se mantiene para no romper la firma del componente pago-dividido.
   */
  conservar?: boolean;
}

/**
 * @deprecated Sin uso real desde que el pago dejó de tener borrador: el
 * Paso 3 le pasa siempre null. Se mantiene para no reescribir el
 * componente pago-dividido, que lo usa en su @Input.
 */
export interface PagoItemCheckout {
  orden: number;
  mpa_codigo: MetodoPagoCodigo;
  mpa_descri: string;
  monto: number | null;
  tiene_comprobante: boolean;
}

export interface RegistrarPagoRequest {
  modo: ModoPago;
  mpa_codigo?: MetodoPagoCodigo;
  comprobante?: File | null;
  pagos?: PagoDividRequest[];
}

// ---------- Pantalla final (los 3 casos) ----------

/**
 * REVISION      -> todo el pago lleva comprobante. "Pago en revisión".
 * PARCIAL       -> comprobante + efectivo. Queda saldo contra entrega.
 * CONTRAENTREGA -> sólo efectivo. "Pedido confirmado".
 *
 * Lo decide el backend a partir del catálogo metodo_pago, no esta pantalla.
 */
export type CasoResultadoPago = 'REVISION' | 'PARCIAL' | 'CONTRAENTREGA';

export interface PagoResultadoItem {
  orden: number;
  mpa_codigo: MetodoPagoCodigo;
  mpa_descri: string;
  monto: number;
  tiene_comprobante: boolean;
}

export interface EntregaResultado {
  modalidad: ModalidadEntrega;
  receptor: string;

  suc_nombre?: string | null;
  suc_direccion?: string | null;
  suc_horario?: string | null;

  direccion?: string | null;
  ciudad?: string | null;
  departamento?: string | null;
  referencia?: string | null;
}

export interface ResultadoPago {
  ped_numero: string;
  ped_total: number;
  ped_creado: string;
  caso: CasoResultadoPago;
  estado_pago: string;
  pagos: PagoResultadoItem[];
  monto_en_revision: number;
  monto_contraentrega: number;
  entrega: EntregaResultado;
}