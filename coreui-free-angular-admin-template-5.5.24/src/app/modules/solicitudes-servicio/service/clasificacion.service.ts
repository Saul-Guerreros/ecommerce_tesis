import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, shareReplay } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { Clasificacion, ClasificacionConfig, CorreccionPayload } from '../models/clasificacion.model';

type Respuesta = { message: string; data: Clasificacion };

/** Punto 7 - API de clasificación automática (bandeja y técnico). */
@Injectable({ providedIn: 'root' })
export class ClasificacionService {
  private apiUrl = `${environment.apiUrl}/admin/servicios/clasificacion`;
  private config$?: Observable<ClasificacionConfig>;

  constructor(private http: HttpClient) {}

  /** Catálogos cacheados: cambian muy poco. */
  getConfig(): Observable<ClasificacionConfig> {
    return this.config$ ??= this.http.get<ClasificacionConfig>(`${this.apiUrl}/config`)
      .pipe(shareReplay({ bufferSize: 1, refCount: false }));
  }

  show(sseId: number): Observable<{ data: Clasificacion | null }> {
    return this.http.get<{ data: Clasificacion | null }>(`${this.apiUrl}/${sseId}`);
  }

  corregir(sseId: number, datos: CorreccionPayload): Observable<Respuesta> {
    return this.http.put<Respuesta>(`${this.apiUrl}/${sseId}`, datos);
  }

  confirmar(sseId: number): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${sseId}/confirmar`, {});
  }

  reclasificar(sseId: number): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.apiUrl}/${sseId}/reclasificar`, {});
  }
}