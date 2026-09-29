import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { URL_SERVICIOS } from '../../config/config';
import { ConfigFiltros, MetaBusqueda } from '../models/filtro-productos.model';
import { ProductoVitrina } from '../models/vitrina.model';

export interface FiltrosBusqueda {
  categorias?: number[];
  marcas?: number[];
  precio_min?: number | null;
  precio_max?: number | null;
  en_oferta?: boolean;
  orden?: 'nuevos' | 'precio_asc' | 'precio_desc';
  busqueda?: string;
  page?: number;
  por_pagina?: number;
}

@Injectable({ providedIn: 'root' })
export class FiltroProductosService {
  private readonly URL = `${URL_SERVICIOS}/tienda/productos`;

  constructor(private http: HttpClient) {}

  configFiltros() {
    return this.http.get<{ data: ConfigFiltros }>(`${this.URL}/filtros`);
  }

  buscar(filtros: FiltrosBusqueda) {
    let params = new HttpParams();

    (filtros.categorias ?? []).forEach((id) => (params = params.append('categorias[]', id)));
    (filtros.marcas ?? []).forEach((id) => (params = params.append('marcas[]', id)));
    if (filtros.precio_min) params = params.set('precio_min', filtros.precio_min);
    if (filtros.precio_max) params = params.set('precio_max', filtros.precio_max);
    if (filtros.en_oferta) params = params.set('en_oferta', '1');
    if (filtros.orden) params = params.set('orden', filtros.orden);
    if (filtros.busqueda) params = params.set('busqueda', filtros.busqueda);
    params = params.set('page', filtros.page ?? 1);
    params = params.set('por_pagina', filtros.por_pagina ?? 12);

    return this.http.get<{ data: ProductoVitrina[]; meta: MetaBusqueda }>(`${this.URL}/buscar`, { params });
  }
}