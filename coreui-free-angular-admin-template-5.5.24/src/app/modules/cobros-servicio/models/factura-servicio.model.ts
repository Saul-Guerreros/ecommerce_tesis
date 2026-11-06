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

/** GET .../factura (FacturaServicioConsultaService::resumenPanel). null = sin factura. */
export interface FacturaServicio {
  ven_id: number;
  numero: string;
  timbrado: string;
  fecha: string | null;
  total: number;
  razon_social: string;
  documento: string;
  tipo_doc: TipoDocumentoFiscal;
  email: string | null;
  telefono: string | null;
  recibe: CanalFactura | null;
  fe: {
    estado: 'PENDIENTE' | 'APROBADA' | 'RECHAZADA';
    cdc: string | null;
    ambiente: string | null;
    prueba: boolean;
    mensaje: string | null;
    aviso: string | null;
    error: string | null;
    qr: string | null;
    intentos: number;
    tiene_pdf: boolean;
    tiene_xml: boolean;
    correo: { enviado: boolean; detalle: string | null } | null;
  } | null;
  /** Lo decide el backend. */
  acciones: { reintentar: boolean; reenviar: boolean };
}
