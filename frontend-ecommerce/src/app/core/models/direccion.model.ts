export interface Direccion {
  dir_id: number;
  dir_barid: number;
  barrio: string | null;
  ciudad: string | null;
  dir_calid: number;
  calle: string | null;
  dir_nrocasa: string | null;
  dir_referencia: string | null;
  dir_celular: string | null;
  dir_telef: string | null;
  dir_predet: boolean;
}

export interface CiudadCatalogo {
  ciu_id: number;
  ciu_descri: string;
  dep_descri: string;
}

export interface BarrioCatalogo {
  bar_id: number;
  bar_ciuid: number;
  bar_descri: string;
}

export interface CalleCatalogo {
  cal_id: number;
  cal_barid: number;
  cal_descri: string;
}

export interface CatalogoDirecciones {
  ciudades: CiudadCatalogo[];
  barrios: BarrioCatalogo[];
  calles: CalleCatalogo[];
}