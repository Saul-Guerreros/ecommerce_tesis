import { Contacto, Persona, TipoContacto, TipoDocumento } from '../../clientes/models/cliente.model';

// Persona, Contacto y TipoDocumento son los mismos que usa Clientes.
export type { Contacto, Persona, TipoContacto, TipoDocumento };

export interface Cargo {
  crg_id: number;
  crg_descr: string;
}

export interface Funcionario {
  fun_id: number;
  fun_fecing: string;
  fun_fecegr: string | null;
  fun_estado: number;
  persona: Persona;
  cargo?: Cargo;
  contactos: Contacto[];
}

/** Payload plano que espera StoreFuncionarioRequest */
export interface FuncionarioPayload {
  per_id?: number;
  per_tpdid: number | null;
  per_cedula: string;
  per_nombr1: string;
  per_nombr2: string | null;
  per_apell1: string;
  per_apell2: string | null;
  per_estciv: string | null;
  per_fechna: string | null;
  fun_crgid: number | null;
  fun_fecing: string;
  fun_fecegr: string | null;
  fun_estado: number;
  contactos: Contacto[];
}

export interface FuncionarioConfig {
  tipos_documento: TipoDocumento[];
  cargos: Cargo[];
  estados_civiles: string[];
  tipos_contacto: TipoContacto[];
}

export interface BusquedaPersona {
  existe: boolean;
  es_funcionario?: boolean;
  funcionario_id?: number;
  persona?: Persona;
  contactos?: Contacto[];
}

export interface ListaFuncionarios {
  total: number;
  data: Funcionario[];
}
