/**
 * Contratos del detalle del pedido. Reflejan PedidoDetalleResource,
 * PedidoPagoDetalleResource y DetallePedidoResource.
 */

export interface TransicionEstado {
  codigo: string;
  descripcion: string;
  habilitado: boolean;
  motivo: string | null;
}

export interface ClienteDetalle {
  cli_id: number | null;
  nombre: string;
  cedula: string | null;
  correo: string | null;
  telefono: string | null;
  tipo: string;
  cliente_desde: string | null;
  pedidos_anteriores: number;
}

export interface ReceptorEntrega {
  nombre: string;
  cedula: string | null;
  telefono: string | null;
  es_tercero: boolean;
}

export interface SucursalEntrega {
  nombre: string | null;
  direccion: string | null;
  horario: string | null;
  ciudad: string | null;
}

export interface DireccionEntrega {
  calle: string | null;
  nro_casa: string | null;
  barrio: string | null;
  ciudad: string | null;
  departamento: string | null;
  referencia: string | null;
}

export interface EntregaDetalle {
  modalidad: 'RETIRO' | 'ENVIO';
  modalidad_label: string;
  receptor: ReceptorEntrega;
  sucursal: SucursalEntrega | null;
  direccion: DireccionEntrega | null;
}

export interface ProductoLinea {
  dpd_id: number;
  prd_id: number;
  descripcion: string;
  cantidad: number;
  precio: number;
  iva_tasa: number;
  iva_monto: number;
  subtotal: number;
  total: number;
  modalidad: string;
}

export interface TotalesPedido {
  subtotal: number;
  iva_incluido: number;
  costo_envio: number;
  total: number;
}

export interface FacturaPedido {
  ven_id: number;
  numero: string;
  fe_estado: number | null;       // 1 Pendiente, 2 Enviada, 3 Rechazada; null = sin factura electrónica
  puede_reenviar: boolean;
  correo_enviado: boolean | null; // null = no se intentó mandar por correo
  correo_detalle: string | null;  // destinatario si salió; motivo si no salió
  empresa: string;
  tiene_pdf: boolean;
}

export interface FacturacionDetalle {
  tipo_documento: 'CI' | 'RUC';
  tipo_label: string;
  nro_documento: string | null;
  razon_social: string | null;
  telefono: string | null;
  correo: string | null;
  recibe_por: 'CORREO' | 'WHATSAPP';
  destino: string | null;
  factura: FacturaPedido | null;
  comprobante_emitido: boolean;
  /** Habilitación de [GENERAR FACTURA]; la regla real vive en la API. */
  puede_generar: boolean;
}

export interface ValidacionComprobante {
  observacion: string | null;
  por: string | null;
  fecha: string | null;
}

export interface ComprobantePago {
  pco_id: number;
  archivo: string;
  url: string;
  subido: string | null;
  /** El admin lo comparó con el banco y lo dio por bueno. Habilita el cobro. */
  validado: boolean;
  validacion: ValidacionComprobante | null;
}

/** Entrada de dinero registrada contra la factura (cobro_venta). */
export interface CobroPago {
  cob_id: number;
  monto: number;
  metodo: string | null;
  nro_transaccion: string | null;
  banco: string | null;
  fecha: string | null;
  observacion: string | null;
  por: string | null;
  registrado: string | null;
}

export interface VerificacionPago {
  monto_verificado: number | null;
  nro_transaccion: string | null;
  banco: string | null;
  fecha_pago: string | null;
  por: string | null;
  fecha: string | null;
}

export interface RechazoPago {
  motivo_id: number;
  motivo: string | null;
  motivo_codigo: string | null;
  detalle: string | null;
  faltante: number | null;
}

/** Confirmación de que el papel llegó a la sucursal. */
export interface RecepcionCheque {
  fecha: string | null;
  por: string | null;
  observacion: string | null;
}

export interface RechazoCheque {
  motivo: string | null;
  detalle: string | null;
}

/**
 * Cheque de un pago. Espeja el bloque `cheque` de
 * PedidoPagoDetalleResource.
 *
 * `vencido` lo decide el backend y no el navegador: el reloj de la máquina
 * del administrador puede estar corrido, y de ese dato depende que el
 * pedido se anule.
 */
export interface ChequePago {
  pch_id: number;
  tipo: 'VISTA' | 'DIFERIDO';
  tipo_label: string;
  diferido: boolean;

  banco: string | null;
  nombre: string | null;
  nrodoc: string | null;
  nro: string | null;
  fecha_cobro: string | null;

  estado: 'EN_ESPERA' | 'DESCARTADO' | 'RECIBIDO' | 'ACREDITADO' | 'NO_ENTREGADO' | 'RECHAZADO';
  estado_label: string;

  /** ISO del plazo de 48 h, para la cuenta regresiva. */
  vence: string | null;
  vence_label: string | null;
  vencido: boolean;

  recepcion: RecepcionCheque | null;
  rechazo: RechazoCheque | null;

  puede_editar: boolean;
  puede_descartar: boolean;
  puede_recibir: boolean;
  puede_rechazar: boolean;
  /** Por qué [Confirmar recepción] está deshabilitado. */
  falta_datos: boolean;
}

export interface PagoItem {
  ppa_id: number;
  orden: number;
  metodo: {
    mpa_id: number | null;
    codigo: string | null;
    descri: string | null;
    requcom: boolean;
  };
  monto_asignado: number;
  estado: { codigo: string | null; descripcion: string | null };
  comprobante: ComprobantePago | null;
  /** Sólo en los pagos con método CHEQUE. */
  cheque: ChequePago | null;
  cobro: CobroPago | null;
  /** Reglas calculadas por la API: la pantalla las repite, no las decide. */
  puede_validar: boolean;
  puede_cobrar: boolean;
  /** Sólo con datos: pedidos cobrados con el flujo anterior a cobro_venta. */
  verificacion: VerificacionPago | null;
  rechazo: RechazoPago | null;
  observacion: string | null;
}

export interface BloquePago {
  dividido: boolean;
  cantidad: number;
  total_pedido: number;
  monto_verificado: number;
  monto_pendiente: number;
  porcentaje: number;
  /** Con alguno pendiente la factura no se puede emitir y el cobro queda bloqueado. */
  comprobantes_pendientes: number;
  /** Contador del encabezado: «1 de 2 cheques recibidos». */
  cheques_total: number;
  cheques_recibidos: number;
  /** El cobro se aplica contra la factura: sin factura no hay cobro. */
  cobro_habilitado: boolean;
  rechazos: number;
  rechazos_restantes: number;
  items: PagoItem[];
}

/** Eje "Cheque" de la cabecera. null si el pedido no tiene cheques. */
export interface EstadoChequePedido {
  codigo: string;
  descripcion: string;
  total: number;
  recibidos: number;
}

export interface PedidoDetalle {
  ped_id: number;
  numero: string | null;
  creado: string | null;
  actualizado: string | null;
  estado_pago: { codigo: string | null; descripcion: string | null };
  estado_pedido: { codigo: string | null; descripcion: string | null };
  estado_cheque: EstadoChequePedido | null;
  /** Plazo de PAGO. Nunca coincide con ped_vencret: son los dos relojes del documento, nunca corren a la vez. */
  ped_vence: string | null;
  /** Plazo de RETIRO en sucursal. Sólo con el pedido en LISTO y entrega RETIRO. */
  ped_vencret: string | null;
  /** El barrido no cancela solo un retiro vencido con pago confirmado: esta bandera es lo que lo señala acá. */
  retiro_vencido: boolean;
  transiciones: TransicionEstado[];
  nota_interna: string | null;
  cliente: ClienteDetalle;
  entrega: EntregaDetalle | null;
  productos: ProductoLinea[];
  totales: TotalesPedido;
  facturacion: FacturacionDetalle | null;
  pago: BloquePago;
}

// ---------- Payloads ----------
export interface ValidarComprobantePayload {
  confirma_monto: boolean;
  observacion?: string | null;
}

export interface RegistrarCobroPayload {
  monto_verificado: number;
  nro_transaccion?: string | null;
  banco?: string | null;
  fecha_pago?: string | null;
  observacion?: string | null;
  confirmar_diferencia?: boolean;
}

export interface RegistrarCobroPayload {
  monto_verificado: number;
  nro_transaccion?: string | null;
  banco?: string | null;
  fecha_pago?: string | null;
  observacion?: string | null;
  confirmar_diferencia?: boolean;
}

export interface RechazarPagoPayload {
  motivo_id: number;
  detalle?: string | null;
  monto_verificado?: number | null;
}

export interface MotivoRechazo {
  mrp_id: number;
  mrp_codigo: string;
  mrp_descri: string;
  mrp_requdet: boolean;
}

/** Producto sin existencias devuelto por el 409 de StockInsuficienteException. */
export interface FaltanteStock {
  producto: string;
  requiere: number;
  disponible: number;
}

// ---------- Cheques ----------

export interface ActualizarChequePayload {
  tipo: 'VISTA' | 'DIFERIDO';
  banco: string;
  nombre: string;
  nrodoc: string;
  nro: string;
  fecha_cobro?: string | null;
}

export interface DescartarChequePayload {
  confirma: boolean;
  motivo?: string | null;
}

export interface RecibirChequePayload {
  confirma_monto: boolean;
  fecha_recepcion?: string | null;
  observacion?: string | null;
}

export interface RechazarChequePayload {
  motivo_id: number;
  detalle?: string | null;
  confirma: boolean;
}

/**
 * Respuesta de /gestion/config.
 *
 * Los motivos vienen separados por ámbito desde la API: el modal del
 * comprobante no debe ofrecer «No entregado en plazo» ni el del cheque
 * «El comprobante es ilegible».
 */
export interface GestionConfig {
  motivos_rechazo: MotivoRechazo[];
  motivos_rechazo_cheque: MotivoRechazo[];
  cheque: {
    tipos: Record<string, string>;
    estados: Record<string, string>;
    horas_plazo: number;
    /** Si true, confirmar la recepción descuenta stock ahora mismo. */
    stock_al_recibir: boolean;
  };
}