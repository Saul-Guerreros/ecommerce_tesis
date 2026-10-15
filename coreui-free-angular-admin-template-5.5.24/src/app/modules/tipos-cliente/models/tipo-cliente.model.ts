export interface TipoCliente {
  tpc_id?: number;
  tpc_descri: string;
  tpc_estado?: number;
  tpc_creado?: string;

  // calculado por el backend
  usos_count?: number;
}