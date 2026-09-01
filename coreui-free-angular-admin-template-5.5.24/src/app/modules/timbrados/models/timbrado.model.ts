export interface Timbrado {
  tim_id?: number;
  tim_empid: number | null;
  tim_sucid: number | null;
  tim_tcoid: number | null;

  tim_numero: string;
  tim_establec: string;
  tim_puntexp: string;

  tim_fechin: string | null;
  tim_fechfn: string | null;

  tim_nroini: number | null;
  tim_nrofin: number | null;
  tim_nroact?: number;

  tim_observacion?: string | null;
  tim_estado?: number;
  tim_creado?: string;

  // calculados por el backend
  suc_nombre?: string;
  emp_razonsocial?: string;
  tco_descri?: string;
  tco_codigo?: string;
  disponibles?: number;
  usos_count?: number;
  vigente?: boolean;
  situacion?: string;
}

export interface EmpresaOption {
  emp_id: number;
  emp_razonsocial: string;
  emp_ruc: string;
}

export interface SucursalOption {
  suc_id: number;
  suc_nombre: string;
  suc_empid: number;
}

export interface ComprobanteOption {
  tco_id: number;
  tco_codigo: string;
  tco_descri: string;
}

export interface TimbradoConfig {
  empresas: EmpresaOption[];
  sucursales: SucursalOption[];
  comprobantes: ComprobanteOption[];
}
