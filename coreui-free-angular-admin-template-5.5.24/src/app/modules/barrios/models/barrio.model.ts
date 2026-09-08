export interface Barrio {
  bar_id?: number;
  bar_ciuid: number | null;
  bar_descri: string;
  bar_estado?: number;          // 0 = inactivo, 1 = activo
  bar_creado?: string;

  // calculados por el backend
  ciu_descri?: string;
  dep_descri?: string;
  usos_count?: number;
  usos_detalle?: { [tabla: string]: number };
}

export interface CiudadOption {
  ciu_id: number;
  ciu_descri: string;
  dep_descri: string;
}
