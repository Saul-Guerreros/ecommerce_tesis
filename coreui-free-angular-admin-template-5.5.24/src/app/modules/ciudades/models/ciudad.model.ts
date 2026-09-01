export interface Ciudad {
  ciu_id?: number;
  ciu_depid: number | null;
  ciu_descri: string;
  ciu_estado?: number;
  ciu_creado?: string;

  // calculados por el backend
  dep_descri?: string;
  usos_count?: number;
  usos_detalle?: { [tabla: string]: number };
}

export interface DepartamentoOption {
  dep_id: number;
  dep_descri: string;
}