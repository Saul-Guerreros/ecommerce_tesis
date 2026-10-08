import { Direccion } from './direccion.model';
import { DatoFiscal } from './dato-fiscal.model';

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

// ---------- Paso 2 — Sección 1: Facturación ----------

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
  /** Lo envia el backend desde el Paso 2; es el total que se debe cubrir. */
  total?: number;
}

export interface ConfigFacturacionResponse {
  titular: TitularFacturacion;
  datos_fiscales: DatoFiscal[];
  entrega: EntregaCheckout | null;
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

// ---------- Paso 2 — Sección 2: Método de Pago ----------

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

/** Un pago ya guardado en el borrador (lo que devuelve el backend). */
export interface PagoItemCheckout {
  orden: number;
  mpa_codigo: MetodoPagoCodigo;
  mpa_descri: string;
  monto: number | null;
  tiene_comprobante: boolean;
}

/**
 * mpa_codigo / mpa_descri / tiene_comprobante solo traen valor cuando
 * modo === 'unico'. Con pago dividido la informacion real esta en pagos[].
 */
export interface PagoCheckout {
  modo: ModoPago;
  pagos: PagoItemCheckout[];
  total_asignado: number;
  mpa_codigo: MetodoPagoCodigo | null;
  mpa_descri: string | null;
  tiene_comprobante: boolean;
}

export interface ConfigPagoResponse {
  metodos: MetodoPagoOpcion[];
  datos_transferencia: DatosTransferencia;
  datos_billetera: DatosBilletera;
  mensaje_efectivo: string | null;
  /** Total exigible calculado por el backend. Nunca se recalcula en Angular. */
  total_pedido: number;
  pago: PagoCheckout | null;
}

/** Un bloque de pago tal como lo arma el formulario del Paso 2. */
export interface PagoDividRequest {
  mpa_codigo: MetodoPagoCodigo;
  monto: number;
  comprobante: File | null;
  /** true = el pago ya tenia comprobante cargado y el cliente no lo cambio. */
  conservar: boolean;
}

export interface GuardarPagoRequest {
  modo: ModoPago;
  mpa_codigo?: MetodoPagoCodigo;
  comprobante?: File | null;
  pagos?: PagoDividRequest[];
}

// ---------- Paso 3 — Confirmación ----------

export interface ResumenConfirmacion extends ResumenCompra {
  total: number;
}

export interface ConfigConfirmacionResponse {
  items: import('./carrito.model').CarritoItem[];
  entrega: EntregaCheckout;
  facturacion: FacturacionCheckout;
  pago: PagoCheckout;
  resumen: ResumenConfirmacion;
}

export interface PedidoConfirmado {
  ped_id: number;
  ped_numero: string;
  ped_total: number;
  ped_creado: string;
}