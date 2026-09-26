import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { PublicacionListResponse, PublicacionWebResponse } from '../models/publicacion-web.model';

export interface PublicacionFiltros {
  search?: string;
  categoria?: number | null;
  estado?: number | null;
  page?: number;
  per_page?: number;
}

@Injectable({
  providedIn: 'root',
})
export class PublicacionWebService {
  private readonly productosUrl = `${environment.apiUrl}/admin/productos`;

  constructor(private http: HttpClient) {}

  /** GET /productos/{id}/publicacion -> data: PublicacionWeb | null */
  obtener(productoId: number): Observable<PublicacionWebResponse> {
    return this.http.get<PublicacionWebResponse>(`${this.productosUrl}/${productoId}/publicacion`);
  }

  /**
   * POST /productos/{id}/publicacion -> "upsert": el backend crea la
   * publicación si el producto todavía no tenía una, o la actualiza si
   * ya existía. Un único método para no duplicar el flujo en el form.
   */
  guardar(productoId: number, formData: FormData): Observable<PublicacionWebResponse> {
    return this.http.post<PublicacionWebResponse>(`${this.productosUrl}/${productoId}/publicacion`, formData);
  }

  eliminarImagen(productoId: number, imagenId: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(
      `${this.productosUrl}/${productoId}/publicacion/imagenes/${imagenId}`
    );
  }

  /** GET /publicaciones -> listado general (apartado "Publicación Web" del dashboard) */
  listar(filtros: PublicacionFiltros = {}): Observable<PublicacionListResponse> {
    let params = new HttpParams();

    Object.entries(filtros).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        params = params.set(key, value as string | number);
      }
    });

    return this.http.get<PublicacionListResponse>(`${environment.apiUrl}/publicaciones`, { params });
  }

  /** Construye el FormData a partir del formulario reactivo + archivos seleccionados */
  construirFormData(valor: Record<string, any>, archivos: File[]): FormData {
    const formData = new FormData();

    Object.entries(valor).forEach(([key, val]) => {
      // OJO: un campo nullable (ej. pub_destacado_orden, pub_prevta) puede
      // llegar en `null` cuando el admin lo vacía/desmarca. Si lo omitiéramos
      // acá, el backend nunca recibiría el cambio y jamás podría limpiarlo.
      // Se manda como string vacío: el middleware de Laravel que convierte
      // strings vacíos a null se encarga del resto antes de validar.
      formData.append(key, val === null || val === undefined ? '' : val);
    });

    archivos.forEach((archivo) => formData.append('imagenes[]', archivo));

    return formData;
  }
}