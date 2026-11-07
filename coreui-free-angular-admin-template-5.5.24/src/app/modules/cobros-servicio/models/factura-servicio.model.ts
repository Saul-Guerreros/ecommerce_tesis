/** Datos fiscales de la solicitud (FacturacionServicioService::opciones). */
export type TipoDocumentoFiscal = 'CI' | 'RUC';
export type CanalFactura = 'CORREO' | 'WHATSAPP';

export interface DatoFiscalOpcion {
  dfi_id: number;
  dfi_alias: string;
  dfi_numero: string;
  dfi_razon: string;
  dfi_telefono: string | null;
  dfi_correo: string | null;
  dfi_principal: boolean;
}

export interface EleccionFacturacion {
  tipo_documento: TipoDocumentoFiscal;
  dfi_id: number | null;
  nrodoc: string;
  raznom: string;
  telefono: string | null;
  correo: string | null;
  recibe: CanalFactura;
}

export interface OpcionesFacturacion {
  titular: { tipo_documento: 'CI'; nrodoc: string; raznom: string; telefono: string | null; correo: string | null };
  datos_fiscales: DatoFiscalOpcion[];
  actual: EleccionFacturacion | null;
  /** false = ya tiene factura emitida: los datos no se cambian. */
  editable: boolean;
}

/** PUT .../facturacion: lo que viaja al backend. */
export interface GuardarFacturacion {
  tipo_documento: TipoDocumentoFiscal;
  recibe: CanalFactura;
  dfi_id?: number | null;
  nrodoc?: string | null;
  raznom?: string | null;
  telefono?: string | null;
  correo?: string | null;
}

/** VENTA = factura final; ANTICIPO = tipo_transaccion 9. */
export type TipoFactura = 'VENTA' | 'ANTICIPO';
export type EstadoFe = 'PENDIENTE' | 'APROBADA' | 'RECHAZADA' | 'PREPARADA' | 'RETIRADA' | 'CANCELADA' | 'INUTILIZADA';

/**
 * Anticipo de una factura:
 *   - en la final: lo aplicado y de qué factura de anticipo;
 *   - en el anticipo: aplicado / acreditado / disponible.
 */
export interface AnticipoFactura {
  aplicado: number;
  acreditado?: number;
  disponible?: number;
  factura_final?: string | null;
  total_servicio?: number;
  factura_anticipo?: string | null;
  cdc_anticipo?: string | null;
}

/** FacturaServicioConsultaService::resumenPanel(). */
export interface FacturaServicio {
  ven_id: number;
  tipo: TipoFactura;
  numero: string;
  timbrado: string;
  fecha: string | null;
  total: number;
  estado: 'EMITIDA' | 'COBRADA';
  cobrado: number;
    /** Estado de negocio calculado (VentaCab::estadoDetalle). */
  estado_detalle: { codigo: string; etiqueta: string };
  /** Dinero cobrado de más que hay que devolver. */
  por_devolver: number;
  anticipo: AnticipoFactura | null;
  razon_social: string;
  documento: string;
  tipo_doc: TipoDocumentoFiscal;
  email: string | null;
  telefono: string | null;
  recibe: CanalFactura | null;
  fe: {
    estado: EstadoFe;
    /** PREPARADA: archivos provisionales, aprobación en curso. */
    provisional: boolean;
    /** CDC + XML + PDF guardados: ya se puede registrar el cobro. */
    documento: boolean;
    cdc: string | null;
    ambiente: string | null;
    prueba: boolean;
    mensaje: string | null;
    aviso: string | null;
    error: string | null;
    reintentable: boolean;
    corregible: boolean;
    /** Rechazada, retirada, cancelada o inutilizada: no es un documento vigente. */
    sin_validez: boolean;
    qr: string | null;
    intentos: number;
    tiene_pdf: boolean;
    tiene_xml: boolean;
    correo: { enviado: boolean; pendiente: boolean; programado: boolean; detalle: string | null } | null;
  } | null;
  /** Lo decide el backend. */
    /** Lo decide el backend (anular ya incluye el permiso). */
  acciones: { reintentar: boolean; reenviar: boolean; anular: boolean };
  
}

/** Factura anulada de la solicitud (trazabilidad). */
export interface FacturaAnulada {
  ven_id: number;
  tipo: TipoFactura;
  numero: string;
  fecha: string | null;
  total: number;
  anulada: string | null;
  motivo: string | null;
  por: string | null;
  fe: { estado: EstadoFe; mensaje: string | null } | null;
}
