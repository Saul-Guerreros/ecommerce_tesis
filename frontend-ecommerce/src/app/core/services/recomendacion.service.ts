import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { URL_SERVICIOS } from '../../config/config';
import { RecomendacionResponse } from '../models/recomendacion.model';

/**
 * Sistema de recomendación. El interceptor ya agrega el JWT (si hay sesión)
 * y X-Visitante, así que el backend personaliza también a invitados.
 */
@Injectable({ providedIn: 'root' })
export class RecomendacionService {
  private readonly base = `${URL_SERVICIOS}/tienda/recomendaciones`;

  constructor(private http: HttpClient) {}

  paraTi(limite = 8): Observable<RecomendacionResponse> {
    return this.http.get<RecomendacionResponse>(`${this.base}/para-ti`, { params: { limite } });
  }

  similares(slug: string, limite = 8): Observable<RecomendacionResponse> {
    return this.http.get<RecomendacionResponse>(
      `${this.base}/producto/${encodeURIComponent(slug)}`, { params: { limite } });
  }

  tendencias(limite = 8): Observable<RecomendacionResponse> {
    return this.http.get<RecomendacionResponse>(`${this.base}/tendencias`, { params: { limite } });
  }
}