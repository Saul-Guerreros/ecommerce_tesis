import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Producto, ProductoListResponse, ProductoResponse } from '../models/producto.model';

export interface ProductoFiltros {
  search?: string;
  categoria?: number | null;
  marca?: number | null;
  estado?: number | null;
  estado_publicacion?: string | null;
  incluir_variantes?: boolean;
  // 0 = solo productos de venta · 1 = solo repuestos de taller (Servicio Técnico).
  repuesto?: number | null;
  // Lista solo las variantes de este producto (filas hijas al expandir).
  padre?: number | null;
  page?: number;
  per_page?: number;
}

@Injectable({
  providedIn: 'root',
})
export class ProductoService {
  private readonly baseUrl = `${environment.apiUrl}/admin/productos`;

  constructor(private http: HttpClient) {}

    listar(filtros: ProductoFiltros = {}): Observable<ProductoListResponse> {
    let params = new HttpParams();

    Object.entries(filtros).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        params = params.set(key, value);
      }
    });

    return this.http.get<ProductoListResponse>(this.baseUrl, { params });
  }

  obtener(id: number): Observable<ProductoResponse> {
    return this.http.get<ProductoResponse>(`${this.baseUrl}/${id}`);
  }

  // Ya no maneja archivos (las imágenes viven en Publicación Web), por eso
  // vuelve a viajar como JSON plano en vez de FormData.
  crear(datos: Partial<Producto>): Observable<ProductoResponse> {
    return this.http.post<ProductoResponse>(this.baseUrl, datos);
  }

  actualizar(id: number, datos: Partial<Producto>): Observable<ProductoResponse> {
    return this.http.put<ProductoResponse>(`${this.baseUrl}/${id}`, datos);
  }

  eliminar(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.baseUrl}/${id}`);
  }
}
