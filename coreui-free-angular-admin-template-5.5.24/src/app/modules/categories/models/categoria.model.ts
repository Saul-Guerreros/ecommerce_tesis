export interface Categoria {
  cat_id?: number;
  cat_idpadr?: number | null;
  cat_nivel?: number;
  cat_nombre: string;
  cat_icono?: string | null; 
  padre?: {
    cat_id: number;
    cat_nombre: string;
  };
}