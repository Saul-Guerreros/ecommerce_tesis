export interface TarifaEnvio {
  tev_id?: number;
  tev_sucid: number | null;
  tev_ciuid: number | null;
  tev_costo: number;
  tev_creado?: string;

  // calculados por el backend (whenLoaded en el Resource)
  sucursal?: string;
  ciudad?: string;
}

export interface SucursalOption {
  suc_id: number;
  suc_empid: number;
  suc_nombre: string;
  emp_razonsocial: string;
}

export interface CiudadOption {
  ciu_id: number;
  ciu_descri: string;
  dep_descri: string;
}