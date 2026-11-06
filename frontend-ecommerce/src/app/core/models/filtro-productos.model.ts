export interface CategoriaFiltro {
  cat_id: number;
  cat_nombre: string;
  productos_count: number;
}

export interface MarcaFiltro {
  mar_id: number;
  mar_descri: string;
  productos_count: number;
}

export interface ConfigFiltros {
  categorias: CategoriaFiltro[];
  marcas: MarcaFiltro[];
  precio_min: number;
  precio_max: number;
}

export interface MetaBusqueda {
  total: number;
  page: number;
  por_pagina: number;
  ultima_pagina: number;
  /** Consulta corregida por el buscador ("notbook" -> "notebook"); null si no hubo corrección. */
  consulta_corregida?: string | null;
  /** todos | parcial | aproximado | sin_resultados | ia (ver ResultadoBusqueda en el backend). */
  modo?: string | null;
  /** Lo que entendió la IA cuando la búsqueda necesitó interpretación. */
  interpretacion?: {
    explicacion: string;
    /** true si se quitaron los filtros técnicos porque no había resultados con ellos. */
    filtros_relajados: boolean;
  } | null;

  /** Id de la búsqueda registrada (análisis de comportamiento); null en página 2+ o sin texto. */
  busqueda_id?: number | null;
}

/** Producto resumido para los resultados instantáneos del buscador del header. */
export interface SugerenciaProducto {
  pub_id: number;
  titulo: string;
  slug: string | null;
  categoria: string | null;
  imagen: string | null;
  precio_original: number;
  precio_final: number;
  descuento_pct: number | null;
}

export interface SugerenciasResponse {
  data: SugerenciaProducto[];
  meta: { total: number; consulta_corregida: string | null };
}