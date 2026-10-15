export interface TipoDocumento {
  tpd_id?: number;
  tpd_codigo: string;
  tpd_descri: string;
  tpd_estado?: number;
  tpd_creado?: string;

  // calculados por el backend
  usos_count?: number;
  /** true = el sistema lo busca por código (CI, RUC): no se elimina, desactiva ni cambia de código. */
  protegido?: boolean;
}
