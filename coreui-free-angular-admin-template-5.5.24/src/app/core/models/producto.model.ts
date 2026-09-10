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

export interface TipoIva {
  tiv_id: number;
  tiv_descri: string;
  tiv_tasa: number;
}

export interface UnidadMedida {
  umd_id: number;
  umd_codigo: string;
  umd_nombre: string;
}

/**
 * Resumen de la publicación web de un producto, tal como viaja embebido
 * en el listado de productos (ProductoResource::publicacion_web).
 * Para el detalle completo (descripción, todas las imágenes,
 * especificaciones) ver publicacion-web.model.ts.
 */
export interface PublicacionWebResumen {
  pub_id: number;
  pub_titulo: string;
  pub_prevta?: number | null;
  pub_estado: number; // 0 = Borrador, 1 = Publicado, 2 = Archivado
  categoria?: string | null;
  imagen_principal?: string | null;
}

export interface Producto {
  prd_id?: number;
  prd_padre_id?: number | null;
  padre?: Producto | null;
  prd_marid: number;
  prd_tivid: number;
  prd_umdid: number;
  prd_codinv: string;
  prd_descri: string;
  prd_precio: number;
  prd_stock: number;
  prd_stkmin: number;
  prd_estado: number; // 1 activo, 0 inactivo (estado de INVENTARIO, no de publicación)
  prd_afestk: boolean; // si el producto afecta stock (ej. un servicio no afecta)
  prd_creado?: string;
  marca?: Marca;
  tipo_iva?: TipoIva;
  unidad_medida?: UnidadMedida;
  // null = el producto todavía no fue publicado en la web.
  publicacion_web?: PublicacionWebResumen | null;
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


