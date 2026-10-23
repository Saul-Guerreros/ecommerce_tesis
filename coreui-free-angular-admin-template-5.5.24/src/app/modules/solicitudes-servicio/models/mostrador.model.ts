import { SucursalOpcion, TecnicoOpcion } from './solicitud-servicio.model';

/** Ingreso presencial (mostrador): modelos del wizard. */

export interface MostradorConfig {
  sucursales: SucursalOpcion[];
  sucursal_principal: number | null;
  tipos_dispositivo: { tdi_id: number; tdi_codigo: string; tdi_descri: string }[];
  tipos_documento: { tpd_id: number; tpd_codigo: string; tpd_descri: string }[];
  tecnicos: TecnicoOpcion[];
  max_fotos: number;
  foto_max_kb: number;
}

export interface ClienteMostrador {
  cli_id: number;
  cli_estado: number;
  nombre: string | null;
  tpd_codigo: string | null;
  documento: string | null;
  celular: string | null;
  whatsapp: string | null;
  correo: string | null;
  tiene_cuenta: boolean;
}

export interface LineaGarantia {
  dpd_id: number;
  ped_id: number;
  ped_numero: string | null;
  producto: string;
  fecha_entrega: string;
  vence: string;
  dias_restantes: number;
  dias_garantia: number;
}

export interface ClienteRapido {
  per_tpdid: number;
  per_cedula: string;
  per_nombr1: string;
  per_nombr2?: string | null;
  per_apell1: string;
  per_apell2?: string | null;
  celular: string;
  es_whatsapp: boolean;
  correo?: string | null;
}

/** Paso 3: servicio entregado que admite un reclamo de garantía. */
export interface ServicioReclamable {
  sse_numero: string;
  equipo: string;
  sse_tdiid: number;
  sse_marca: string;
  sse_modelo: string;
  sse_nroser: string | null;
  entregado: string | null;
  vence: string | null;
}

export type TipoServicioMostrador = 'REPARACION' | 'GARANTIA' | 'RECLAMO';

export interface MostradorRegistrado {
  sse_id: number;
  sse_numero: string;
  cliente: string | null;
  tiene_correo: boolean;
  whatsapp_url: string | null;
}
