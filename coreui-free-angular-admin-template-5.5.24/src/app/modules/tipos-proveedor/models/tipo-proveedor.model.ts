export interface TipoProveedor {
  tpr_id?: number;
  tpr_descri: string;
  tpr_estado?: number;
  tpr_creado?: string;

  // calculado por el backend
  usos_count?: number;
}