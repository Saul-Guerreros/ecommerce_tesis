export interface Categoria {
  cat_id?: number;
  cat_idpadr?: number | null;
  cat_nivel?: number;
  cat_nombre: string;
  padre?: {
    cat_id: number;
    cat_nombre: string;
  };
}