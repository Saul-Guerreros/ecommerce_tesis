/**
 * Shape que devuelve GET /api/productos (ProductoResource del backend),
 * recortado a lo que necesita el storefront público. `publicacion_web`
 * viene null si el producto todavía no fue publicado, y solo aparece
 * en la respuesta cuando se filtra ?estado_publicacion=publicado.
 */
export interface PublicacionWebResumen {
  pub_id: number;
  pub_titulo: string;
  pub_prevta: number | null;
  pub_estado: number;
  categoria: string | null;
  imagen_principal: string | null;
}

export interface Producto {
  prd_id: number;
  prd_codinv: string;
  prd_descri: string;
  prd_precio: number;
  prd_estado?: string | number;
  publicacion_web: PublicacionWebResumen | null;
}

export interface ProductosPaginados {
  data: Producto[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
