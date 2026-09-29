export interface ProductoVitrina {
  pub_id: number;
  prd_id: number;
  sku: string;
  titulo: string;
  slug: string | null;
  categoria: string | null;
  marca: string | null;
  imagen: string | null;
  precio_original: number;
  precio_final: number;

  precio_mayorista?: number | null;
  cantidad_minima_mayorista?: number | null;
  solo_mayorista?: boolean;
  cantidad_minima?: number;

  descuento_pct: number | null;
  oferta_fin: string | null;
  es_flash: boolean;
  rating: number | null;

  stock?: number | null;
  controla_stock?: boolean;
  /** "Solo quedan X en existencia" (backend ya evalúa contra el umbral). */
  poco_stock?: boolean;

  /** Vacío = sin cuotas: la tarjeta muestra solo "Comprá al Contado". */
  cuotas?: CuotaOpcion[];
}

/** Ya resuelta por el backend: el frontend no recalcula nada. */
export interface CuotaOpcion {
  cantidad: number;
  recargo_pct: number;
  monto_cuota: number;
  total: number;
}

/** Lo que emite el cuotero al apretar un botón de carrito. */
export interface SeleccionCompra {
  modalidad: 'contado' | 'credito';
  numero_cuotas?: number;
}

export interface VitrinaHomeResponse {
  data: {
    nuevos: ProductoVitrina[];
    destacados: ProductoVitrina[];
    trending: ProductoVitrina[];
    mas_vendidos: ProductoVitrina[];
    en_oferta: ProductoVitrina[];
    oferta_del_dia: ProductoVitrina[];
  };
}