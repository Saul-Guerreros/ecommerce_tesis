import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable, finalize } from 'rxjs';
import { environment } from '../../../../../environments/environment';
import {
  ApiMessageResponse,
  ConfigVariacionesResponse,
  Especificacion,
  Variacion,
} from '../models/product-variation.model';

// NOTA: se asume que ya tienes un HttpInterceptor que agrega el header
// "Authorization: Bearer <token>" a todas las peticiones.

@Injectable({
  providedIn: 'root',
})
export class ProductVariationsService {
  private apiUrl = `${environment.apiUrl}/admin`;

  isLoadingSubject = new BehaviorSubject<boolean>(false);
  isLoading$: Observable<boolean> = this.isLoadingSubject.asObservable();

  constructor(private http: HttpClient) {}

  private wrap<T>(obs: Observable<T>): Observable<T> {
    this.isLoadingSubject.next(true);
    return obs.pipe(finalize(() => this.isLoadingSubject.next(false)));
  }

  showProduct(producto_id: string | number): Observable<any> {
    // OJO: este endpoint NO va con el prefijo /admin, vive en Route::prefix('productos')
    return this.wrap(this.http.get(`${environment.apiUrl}/productos/${producto_id}`));
  }

  // ==== VARIACIONES ====
  configVariaciones(): Observable<ConfigVariacionesResponse> {
    return this.wrap(this.http.get<ConfigVariacionesResponse>(`${this.apiUrl}/variaciones/config`));
  }

  listVariaciones(producto_id: string | number): Observable<{ variaciones: Variacion[] }> {
    return this.wrap(
      this.http.get<{ variaciones: Variacion[] }>(`${this.apiUrl}/variaciones?producto_id=${producto_id}`)
    );
  }

  createVariacion(data: any): Observable<ApiMessageResponse & { variacion: Variacion }> {
    return this.wrap(this.http.post<any>(`${this.apiUrl}/variaciones`, data));
  }

  updateVariacion(id: number, data: any): Observable<ApiMessageResponse & { variacion: Variacion }> {
    return this.wrap(this.http.put<any>(`${this.apiUrl}/variaciones/${id}`, data));
  }

  deleteVariacion(id: number): Observable<ApiMessageResponse> {
    return this.wrap(this.http.delete<ApiMessageResponse>(`${this.apiUrl}/variaciones/${id}`));
  }

  // ==== ESPECIFICACIONES ====
  // NOTA: reutiliza el listado de atributos/propiedades de tu módulo ya construido
  // (ej. this.http.get(`${this.apiUrl}/attributes`)) para poblar el selector de atributos.

  listEspecificaciones(producto_id: string | number): Observable<{ especificaciones: Especificacion[] }> {
    return this.wrap(
      this.http.get<{ especificaciones: Especificacion[] }>(
        `${this.apiUrl}/especificaciones?producto_id=${producto_id}`
      )
    );
  }
  
  
  duplicarEspecificaciones(productoIdOrigen: number, productoIdDestino: number): Observable<ApiMessageResponse> {
  return this.wrap(this.http.post<ApiMessageResponse>(`${this.apiUrl}/especificaciones/duplicar`, {
    producto_id_origen: productoIdOrigen,
    producto_id_destino: productoIdDestino,
  }));
  }

  createEspecificacion(data: any): Observable<ApiMessageResponse & { especificaciones: Especificacion[] }> {
    return this.wrap(this.http.post<any>(`${this.apiUrl}/especificaciones`, data));
  }

  updateEspecificacion(id: number, data: any): Observable<ApiMessageResponse & { especificacion: Especificacion }> {
    return this.wrap(this.http.put<any>(`${this.apiUrl}/especificaciones/${id}`, data));
  }

  deleteEspecificacion(id: number): Observable<ApiMessageResponse> {
    return this.wrap(this.http.delete<ApiMessageResponse>(`${this.apiUrl}/especificaciones/${id}`));
  }
}
