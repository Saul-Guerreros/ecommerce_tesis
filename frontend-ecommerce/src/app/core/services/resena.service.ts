import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import {EstadoMiResena, OrdenResenas, ProductoPorResenar, ResenaDestacada, ResenaGuardadaResponse,ResenaPayload, ResenasPaginadas, ResumenResenas,} from '../models/resena.model';

/**
 * Reseñas de productos.
 *  - listar(): público (sin sesión).
 *  - el resto exige sesión de cliente; el token lo agrega authInterceptor.
 */
@Injectable({ providedIn: 'root' })
export class ResenaService {
  private readonly URL = `${URL_SERVICIOS}/tienda`;

  constructor(private http: HttpClient) {}

  listar(slug: string, page = 1, orden: OrdenResenas = 'recientes', estrellas: number | null = null): Observable<ResenasPaginadas> {
    let params = new HttpParams().set('page', page).set('orden', orden);
    if (estrellas) params = params.set('estrellas', estrellas);

    return this.http.get<ResenasPaginadas>(`${this.URL}/producto/${encodeURIComponent(slug)}/resenas`, { params });
  }

  mia(slug: string): Observable<{ data: EstadoMiResena }> {
    return this.http.get<{ data: EstadoMiResena }>(`${this.URL}/producto/${encodeURIComponent(slug)}/resenas/mia`);
  }

  crear(slug: string, datos: ResenaPayload): Observable<ResenaGuardadaResponse> {
    return this.http.post<ResenaGuardadaResponse>(`${this.URL}/producto/${encodeURIComponent(slug)}/resenas`, datos);
  }

  actualizar(id: number, datos: ResenaPayload): Observable<ResenaGuardadaResponse> {
    return this.http.put<ResenaGuardadaResponse>(`${this.URL}/resenas/${id}`, datos);
  }

  eliminar(id: number): Observable<{ message: string; resumen: ResumenResenas }> {
    return this.http.delete<{ message: string; resumen: ResumenResenas }>(`${this.URL}/resenas/${id}`);
  }
  
    /** Testimonios del Home (público). */
  destacadas(): Observable<{ data: ResenaDestacada[] }> {
    return this.http.get<{ data: ResenaDestacada[] }>(`${this.URL}/resenas/destacadas`);
  }

  porPedido(numero: string): Observable<{ habilitado: boolean; data: ProductoPorResenar[] }> {
    return this.http.get<{ habilitado: boolean; data: ProductoPorResenar[] }>(
      `${this.URL}/pedidos/${encodeURIComponent(numero)}/resenas`,
    );
  }
}