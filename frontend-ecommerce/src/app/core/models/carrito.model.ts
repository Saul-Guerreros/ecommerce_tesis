/**
 * Propiedad de una variación (ej: "Color: Rojo"), tal como la arma
 * CarritoResource::variacion.propiedades en el backend.
 */
export interface CarritoPropiedad {
  atb_nombre: string;
  prp_nombre: string;
}

/**
 * Variación seleccionada para este ítem del carrito. null cuando el
 * producto se agregó sin variación (producto simple).
 */
export interface CarritoVariacion {
  var_proid: number;
  var_preadi: number;
  propiedades: CarritoPropiedad[];
}

/**
 * Resumen de la ficha web del producto (publicacion_web), lo mínimo para
 * pintar la fila del carrito: título, slug (para el link) e imagen.
 */
export interface CarritoPublicacionWeb {
  pub_titulo: string;
  pub_slug: string | null;
  imagen: string | null;
}

/**
 * Shape de cada fila que devuelve CarritoResource (backend), consumido
 * por GET/POST/PUT /api/tienda/carrito.
 */
export interface CarritoItem {
  car_id: number;
  car_idpro: number;
  car_idvarp: number | null;

  car_tipdes: number | null;
  car_descue: number;
  car_tipcam: number | null;
  car_codcup: string | null;
  car_coddes: string | null;

  car_cantid: number;
  car_precio: number;
  car_subtot: number;
  car_total: number;
  car_creado: string;

  prd_descri: string;
  publicacion_web: CarritoPublicacionWeb | null;
  variacion: CarritoVariacion | null;
}

// ----- Shapes de respuesta, uno por cada fila del contrato de API (punto 6 del estándar) -----

/** GET /api/tienda/carrito */
export interface CarritoListResponse {
  data: CarritoItem[];
}

/** POST y PUT /api/tienda/carrito(/{id}) */
export interface CarritoItemResponse {
  message: string;
  data: CarritoItem;
}

/** DELETE /api/tienda/carrito(/{id}) */
export interface CarritoMensajeResponse {
  message: string;
}