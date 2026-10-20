import { Contacto, Persona, TipoContacto, TipoDocumento } from '../../clientes/models/cliente.model';

// Persona, Contacto y TipoDocumento son los mismos que usa Clientes.
export type { Contacto, Persona, TipoContacto, TipoDocumento };

export interface Cargo {
  crg_id: number;
  crg_descr: string;
}

// En Funcionario, agregar rol_id y rol (igual que en Usuario):
export interface Funcionario {
  fun_id: number;
  fun_fecing: string;
  fun_fecegr: string | null;
  fun_estado: number;
  rol_id?: number | null;
  rol?: string | null;
  persona: Persona;
  cargo?: Cargo;
  contactos: Contacto[];
  /** Cuenta de acceso al panel (null si todavía no tiene). Nunca incluye la contraseña. */
  usuario?: { usu_id: number; email: string; estado: string } | null;
}

// En FuncionarioPayload, agregar rol_id después de fun_crgid:
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
  rol_id: number | null;
  fun_fecing: string;
  fun_fecegr: string | null;
  fun_estado: number;
  contactos: Contacto[];
  /** Acceso al sistema (opcional). En edición, contraseña vacía = no cambiarla. */
  usu_email?: string | null;
  usu_password?: string | null;
}

export interface RolOpcion {
  rol_id: number;
  rol_descri: string;
}

export interface FuncionarioConfig {
  tipos_documento: TipoDocumento[];
  cargos: Cargo[];
  roles: RolOpcion[];
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
