export interface TipoIva {
  tiv_id?: number;
  tiv_descri: string;
  tiv_tasa: number | null;
  tiv_estado?: number;
  tiv_creado?: string;

  // calculado por el backend
  usos_count?: number;
}
