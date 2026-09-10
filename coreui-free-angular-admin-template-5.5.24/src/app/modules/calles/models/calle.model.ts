export interface Calle {
  cal_id?: number;
  cal_barid: number | null;
  cal_descri: string;
  cal_estado?: number;          // 0 = inactivo, 1 = activo
  cal_creado?: string;

  // calculados por el backend (calle -> barrio -> ciudad, sin columna propia)
  bar_descri?: string;
  ciu_descri?: string;
  usos_count?: number;
  usos_detalle?: { [tabla: string]: number };
}

export interface CiudadOption {
  ciu_id: number;
  ciu_descri: string;
  dep_descri: string;
}

export interface BarrioOption {
  bar_id: number;
  bar_ciuid: number;   // permite filtrar el combo por ciudad en el cliente
  bar_descri: string;
}

export interface CalleConfig {
  ciudades: CiudadOption[];
  barrios: BarrioOption[];
}
