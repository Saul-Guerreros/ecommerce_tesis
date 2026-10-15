export interface Cargo {
  crg_id?: number;
  crg_descr: string;
  crg_estado?: number;
  crg_creado?: string;

  // calculado por el backend
  usos_count?: number;
}