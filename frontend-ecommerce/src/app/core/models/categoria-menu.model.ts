/** Categoría principal tal como la devuelve GET /tienda/categorias/menu */
export interface CategoriaMenu {
  cat_id: number;
  cat_nombre: string;
  cat_icono: string | null;
  cat_imagen: string | null; // URL completa o null
  hijas: { cat_id: number; cat_nombre: string }[];
}