import { Categoria } from './producto.model';

export interface ImagenProducto {
  img_id: number;
  img_url: string;
  img_orden: number;
  img_princi: boolean;
}

export interface EspecificacionResumen {
  esp_id: number;
  esp_atbid: number;
  esp_prpid: number;
  esp_valibr: string | null;
  esp_valnum: number | null;
}

export interface ProductoResumen {
  prd_id: number;
  prd_codinv: string;
  prd_descri: string;
  // Precio minorista/mayorista de Gestión de Precios (pueden venir null si
  // el producto todavía no tuvo compra confirmada ni edición manual).
  prd_pvtmin: number | null;
  prd_pvtmay: number | null;
  prd_estado: number;
}

/**
 * Ubicación del producto en las vitrinas del ecommerce (tabla
 * vitrina_producto). Cada campo es el orden dentro de esa sección,
 * o null si el producto no está activo en ella.
 */
export interface VitrinaResumen {
  featured: number | null;
  trending: number | null;
  selling: number | null;
}

/**
 * Ficha pública ("storefront") de un producto: título y descripción para
 * la web, categoría, estado de publicación, imágenes y especificaciones.
 * Relación 1 a 1 con Producto (pub_prdid).
 */
export interface PublicacionWeb {
  pub_id?: number;
  pub_prdid: number;
  pub_catid: number | null;
  pub_titulo: string;
  // Precio propio de la ficha web (puede diferir del precio de inventario
  // del producto: permite ofertas o redondeo comercial sin tocar prd_precio).
  pub_prevta: number | null;
  // URL amigable (/producto/mi-producto-slug). Si se deja vacío, el backend
  // lo autogenera a partir de pub_titulo.
  pub_slug?: string | null;
  pub_descwb?: string | null;
  pub_meta_titulo?: string | null;
  pub_meta_descripcion?: string | null;
  pub_estado: number; // 0 = Borrador, 1 = Publicado, 2 = Archivado
  // Destacar en el home del ecommerce (sección "Featured Products"):
  // null = no destacado; número = posición en esa sección (1 = primero).
  pub_destacado_orden?: number | null;
  pub_creado?: string;
  pub_actualizado?: string;
  producto?: ProductoResumen;
  categoria?: Categoria;
  imagenes?: ImagenProducto[];
  especificaciones?: EspecificacionResumen[];
  vitrina?: VitrinaResumen;
}

export interface PublicacionWebResponse {
  message?: string;
  data: PublicacionWeb | null;
}

export interface PublicacionListResponse {
  data: PublicacionWeb[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}