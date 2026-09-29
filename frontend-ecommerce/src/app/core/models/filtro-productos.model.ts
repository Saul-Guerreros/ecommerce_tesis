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
}