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

export interface FacturacionDetalle {
  tipo_documento: 'CI' | 'RUC';
  tipo_label: string;
  nro_documento: string | null;
  razon_social: string | null;
  telefono: string | null;
  correo: string | null;
  recibe_por: 'CORREO' | 'WHATSAPP';
  destino: string | null;
  comprobante_emitido: boolean;
}

export interface ComprobantePago {
  pco_id: number;
  archivo: string;
  url: string;
  verificado: boolean;
  subido: string | null;
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
  items: PagoItem[];
}

export interface PedidoDetalle {
  ped_id: number;
  numero: string | null;
  creado: string | null;
  actualizado: string | null;
  estado_pago: { codigo: string | null; descripcion: string | null };
  estado_pedido: { codigo: string | null; descripcion: string | null };
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

export interface ConfirmarPagoPayload {
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