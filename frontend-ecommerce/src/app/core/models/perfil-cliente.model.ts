export interface PerfilPersona {
  per_id: number;
  nombre_completo: string;
  per_nombr1: string;
  per_nombr2: string | null;
  per_apell1: string;
  per_apell2: string | null;
  per_cedula: string | null;
  tipo_documento: string | null;
  per_fechna: string | null;
  avatar_url: string | null;
}

export interface PerfilClienteInfo {
  cli_id: number;
  cli_ruc: string | null;
  tipo_cliente: string | null;
}

export interface DireccionResumen {
  dir_id: number;
  barrio: string | null;
  ciudad: string | null;
  calle: string | null;
  dir_nrocasa: string | null;
}

export interface PerfilCliente {
  usuario_id: number;
  email: string;
  estado: string;
  persona: PerfilPersona;
  cliente: PerfilClienteInfo | null;
  celular: string | null;
  direccion_predeterminada: DireccionResumen | null;
}