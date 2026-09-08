export interface Timbrado {
  tim_id?: number;
  tim_tcoid: number | null;
  tim_sucid: number | null;
  tim_numero: string;
  tim_puntex: string;
  tim_fechin: string;   // 'YYYY-MM-DD'
  tim_fechfn: string;
  tim_nroini: number | null;
  tim_nrofin: number | null;
  tim_nroact?: number;   // solo lectura, lo gestiona el sistema
  tim_observ?: string | null;
  tim_estado?: number;
  tim_creado?: string;

  // calculados por el backend
  vigente?: boolean;
  en_uso?: boolean;
  tco_descri?: string;
  tco_codigo?: string;
  suc_nombre?: string;
}

export interface TipoComprobanteOption {
  tco_id: number;
  tco_codigo: string;
  tco_descri: string;
}

export interface SucursalOption {
  suc_id: number;
  suc_nombre: string;
  emp_razonsocial: string;
}

export interface TimbradoConfig {
  tipos: TipoComprobanteOption[];
  sucursales: SucursalOption[];
}