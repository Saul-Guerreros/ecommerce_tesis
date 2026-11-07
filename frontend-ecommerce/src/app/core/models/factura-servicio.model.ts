import { DatoFiscal } from './dato-fiscal.model';
import { MedioRecepcion, TipoDocumentoFacturacion } from './checkout.model';

/** GET /tienda/servicios/{numero}/facturacion (FacturacionServicioService::opciones + factura). */
export interface FacturacionServicioOpciones {
  titular: {
    tipo_documento: 'CI';
    nrodoc: string;
    raznom: string;
    nombre: string;
    apellido: string;
    telefono: string | null;
    correo: string | null;
  };
  datos_fiscales: DatoFiscal[];
  actual: {
    tipo_documento: TipoDocumentoFacturacion;
    dfi_id: number | null;
    nrodoc: string;
    raznom: string;
    telefono: string | null;
    correo: string | null;
    recibe: MedioRecepcion;
  } | null;
  /** false = la factura ya se emitió: los datos no se cambian. */
  editable: boolean;
  factura?: FacturaServicioCliente | null;
  /** Factura del anticipo (tipo_transaccion 9), si el cliente pagó una seña. */
  anticipo?: FacturaServicioCliente | null;
}

/** PUT .../facturacion. Teléfono y correo salen de la cuenta o del RUC guardado. */
export interface GuardarFacturacionServicio {
  tipo_documento: TipoDocumentoFacturacion;
  recibe: MedioRecepcion;
  dfi_id?: number;
}

/** FacturaServicioConsultaService::resumenCliente. */
/** VENTA = factura final; ANTICIPO = factura de la seña. */
export type TipoFacturaCliente = 'VENTA' | 'ANTICIPO';

/** FacturaServicioConsultaService::resumenCliente. */
export interface FacturaServicioCliente {
  tipo: TipoFacturaCliente;
  numero: string;
  fecha: string | null;
  total: number;
  /** Final: anticipo descontado (null si no hubo). */
  anticipo: number | null;
  documento: string;
  nombre: string;
  cdc: string | null;
  qr: string | null;
  prueba: boolean;
  /** true cuando la DNIT la aprobó y el PDF está listo para descargar. */
  disponible: boolean;
    /** Estado simplificado: lo decide el backend. */
  estado: 'DISPONIBLE' | 'EN_PROCESO' | 'CON_PROBLEMA' | 'SIN_FE';
}
