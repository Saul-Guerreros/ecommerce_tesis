export interface Direccion {
  dir_id: number;
  dir_alias: string;
  dir_ciuid: number;
  ciudad: string | null;
  departamento: string | null;
  dir_barrio: string;
  dir_calle: string;
  dir_nrocasa: string | null;
  dir_referencia: string | null;
  dir_celular: string | null;
  dir_telef: string | null;
  dir_predet: boolean;
  resumen: string;
}

export interface DepartamentoCatalogo {
  dep_id: number;
  dep_descri: string;
}

export interface CiudadCatalogo {
  ciu_id: number;
  ciu_depid: number;
  ciu_descri: string;
}

export interface CatalogoDirecciones {
  departamentos: DepartamentoCatalogo[];
  ciudades: CiudadCatalogo[];
}