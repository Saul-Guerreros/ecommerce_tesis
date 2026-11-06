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
}

/** PUT .../facturacion. Teléfono y correo salen de la cuenta o del RUC guardado. */
export interface GuardarFacturacionServicio {
  tipo_documento: TipoDocumentoFacturacion;
  recibe: MedioRecepcion;
  dfi_id?: number;
}

/** FacturaServicioConsultaService::resumenCliente. */
export interface FacturaServicioCliente {
  numero: string;
  fecha: string | null;
  total: number;
  documento: string;
  nombre: string;
  cdc: string | null;
  qr: string | null;
  prueba: boolean;
  /** true cuando la DNIT la aprobó y el PDF está listo para descargar. */
  disponible: boolean;
}
