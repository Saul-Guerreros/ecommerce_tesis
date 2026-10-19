import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Trabajo, TrabajoConfig } from '../models/trabajo.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class TrabajosService {
  private apiUrl = `${environment.apiUrl}/admin/trabajos`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; estado?: string } = {}): Observable<Trabajo[]> {
    let params = new HttpParams();
    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.estado !== undefined && filtros.estado !== '') params = params.set('estado', filtros.estado);

    return this.http.get<{ data: Trabajo[] }>(this.apiUrl, { params }).pipe(map((res) => res.data));
  }

  getConfig(): Observable<TrabajoConfig> {
    return this.http.get<TrabajoConfig>(`${this.apiUrl}/config`);
  }

  create(trabajo: Partial<Trabajo>): Observable<any> {
    return this.http.post(this.apiUrl, trabajo);
  }

  update(id: number, trabajo: Partial<Trabajo>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, trabajo);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}
