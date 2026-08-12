import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ProductoListResponse, ProductoResponse } from '../models/producto.model';

export interface ProductoFiltros {
  search?: string;
  categoria?: number | null;
  marca?: number | null;
  estado?: number | null;
  page?: number;
  per_page?: number;
}

@Injectable({
  providedIn: 'root',
})
export class ProductoService {
  private readonly baseUrl = `${environment.apiUrl}/productos`;

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

  crear(formData: FormData): Observable<ProductoResponse> {
    return this.http.post<ProductoResponse>(this.baseUrl, formData);
  }

  actualizar(id: number, formData: FormData): Observable<ProductoResponse> {
    // Laravel no parsea multipart/form-data en peticiones PUT nativas,
    // por eso se envía como POST con _method=PUT (method spoofing).
    formData.append('_method', 'PUT');
    return this.http.post<ProductoResponse>(`${this.baseUrl}/${id}`, formData);
  }

  eliminar(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.baseUrl}/${id}`);
  }

  eliminarImagen(productoId: number, imagenId: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(
      `${this.baseUrl}/${productoId}/imagenes/${imagenId}`
    );
  }

  /** Construye el FormData a partir del formulario reactivo + archivos seleccionados */
  construirFormData(valor: Record<string, any>, archivos: File[]): FormData {
    const formData = new FormData();

    Object.entries(valor).forEach(([key, val]) => {
      if (val !== null && val !== undefined) {
        formData.append(key, val);
      }
    });

    archivos.forEach((archivo) => formData.append('imagenes[]', archivo));

    return formData;
  }

}
