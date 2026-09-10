export type TipoContacto = 'CELULAR' | 'TELEFONO' | 'WHATSAPP' | 'EMAIL';

export interface Contacto {
  con_id?: number;
  con_tipo: TipoContacto | '';
  con_valor: string;
}

export interface Persona {
  per_id?: number;
  per_tpdid: number | null;
  per_cedula: string;
  per_nombr1: string;
  per_nombr2: string | null;
  per_apell1: string;
  per_apell2: string | null;
  per_estciv: string | null;
  per_fechna: string | null;
  nombre_completo?: string;
}

export interface TipoDocumento {
  tpd_id: number;
  tpd_codigo: string;
  tpd_descri: string;
}

export interface TipoCliente {
  tpc_id: number;
  tpc_descri: string;
}

export interface Cliente {
  cli_id: number;
  cli_ruc: string | null;
  cli_estado: number;
  persona: Persona;
  tipo_cliente?: TipoCliente;
  contactos: Contacto[];
}

/** Payload plano que espera StoreClienteRequest */
export interface ClientePayload {
  per_id?: number;
  per_tpdid: number | null;
  per_cedula: string;
  per_nombr1: string;
  per_nombr2: string | null;
  per_apell1: string;
  per_apell2: string | null;
  per_estciv: string | null;
  per_fechna: string | null;
  cli_tpcid: number | null;
  cli_ruc: string | null;
  cli_estado: number;
  contactos: Contacto[];
}

export interface ClienteConfig {
  tipos_documento: TipoDocumento[];
  tipos_cliente: TipoCliente[];
  estados_civiles: string[];
  tipos_contacto: TipoContacto[];
}

export interface BusquedaPersona {
  existe: boolean;
  es_cliente?: boolean;
  cliente_id?: number;
  persona?: Persona;
  contactos?: Contacto[];
}

export interface ListaClientes {
  total: number;
  data: Cliente[];
}