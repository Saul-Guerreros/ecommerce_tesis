export interface TipoDispositivo {
  tdi_id?: number;
  tdi_codigo: string;
  tdi_descri: string;
  tdi_estado?: number;
  tdi_creado?: string;

  // calculado por el backend
  usos_count?: number;
}
