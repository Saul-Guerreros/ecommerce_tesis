import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { PresupuestoFila, PresupuestoRevision } from '../models/presupuesto-validacion.model';
import { environment } from '../../../../environments/environment';

type Respuesta = { message: string; data: PresupuestoRevision };

@Injectable({ providedIn: 'root' })
export class PresupuestosValidacionService {
  private apiUrl = `${environment.apiUrl}/admin/servicios/presupuestos`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { estado?: string; buscar?: string }): Observable<{ data: PresupuestoFila[]; meta: { total: number } }> {
    let params = new HttpParams().set('estado', filtros.estado ?? '');
    if (filtros.buscar) params = params.set('buscar', filtros.buscar);
    return this.http.get<{ data: PresupuestoFila[]; meta: { total: number } }>(this.apiUrl, { params });
  }

  show(id: number): Observable<{ data: PresupuestoRevision }> {
    return this.http.get<{ data: PresupuestoRevision }>(`${this.apiUrl}/${id}`);
  }

  validar(id: number): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${id}/validar`, {});
  }

  devolver(id: number, obs: string): Observable<Respuesta> {
    return this.http.post<Respuesta>(`${this.apiUrl}/${id}/devolver`, { obs });
  }
}
