export interface Categoria {
  cat_id: number;
  cat_nombre: string;
  cat_idpadr?: number | null;
  cat_nivel?: number | null;
}

export interface Marca {
  mar_id: number;
  mar_descri: string;
}

export interface ImagenProducto {
  img_id: number;
  img_url: string;
  img_orden: number;
  img_princi: boolean;
}

export interface Producto {
  prd_id?: number;
  prd_marid: number;
  prd_catid: number;
  prd_titulo: string;
  prd_codinv: string;
  prd_descri: string;
  prd_precio: number;
  prd_stock: number;
  prd_stkmin: number;
  prd_estado: number; // 1 activo, 0 inactivo
  prd_creado?: string;
  marca?: Marca;
  categoria?: Categoria;
  imagenes?: ImagenProducto[];
  stock_bajo?: boolean;
}

export interface PaginacionMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ProductoListResponse {
  data: Producto[];
  meta: PaginacionMeta;
}

export interface ProductoResponse {
  message?: string;
  data: Producto;
}
