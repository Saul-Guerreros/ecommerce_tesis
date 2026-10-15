export interface UsuarioAuditoria {
  usu_id: number;
  usu_login: string;
  nombre: string | null;
}

/** Respuesta paginada común a las 4 pantallas de auditoría. */
export interface ListaAuditoria<T, R = Record<string, never>> {
  total: number;
  data: T[];
  resumen?: R;
}

export interface HistorialPasswordItem {
  hpa_id: number;
  hpa_iporig: string | null;
  hpa_creado: string;
  usuario: UsuarioAuditoria | null;
}

export type EstadoSesion = 'ACTIVA' | 'EXPIRADA' | 'REVOCADA';

export interface SesionItem {
  ses_id: number;
  ses_iporig: string | null;
  ses_userag: string | null;
  ses_creado: string;
  ses_fecexp: string;
  estado: EstadoSesion;
  usuario: UsuarioAuditoria | null;
}

export interface IntentoSesionItem {
  ise_id: number;
  ise_logusa: string;
  exitoso: boolean;
  ise_obs: string | null;
  ise_iporig: string | null;
  ise_userag: string | null;
  ise_creado: string;
  usuario: UsuarioAuditoria | null;
}

export type EstadoToken = 'VIGENTE' | 'USADO' | 'EXPIRADO';

export interface TokenItem {
  tkv_id: number;
  tkv_tipo: string;
  tipo_label: string;
  tkv_creado: string;
  tkv_fecexp: string;
  estado: EstadoToken;
  usuario: UsuarioAuditoria | null;
}
