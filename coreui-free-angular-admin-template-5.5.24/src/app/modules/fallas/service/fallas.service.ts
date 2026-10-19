import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Falla, FallaConfig } from '../models/falla.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class FallasService {
  private apiUrl = `${environment.apiUrl}/admin/fallas`;

  constructor(private http: HttpClient) {}

  /** tdi_id: id de tipo, 'GEN' para genéricas, '' para todas. */
  getAll(filtros: { search?: string; estado?: string; tdi_id?: string } = {}): Observable<Falla[]> {
    let params = new HttpParams();
    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.estado !== undefined && filtros.estado !== '') params = params.set('estado', filtros.estado);
    if (filtros.tdi_id) params = params.set('tdi_id', filtros.tdi_id);

    return this.http.get<{ data: Falla[] }>(this.apiUrl, { params }).pipe(map((res) => res.data));
  }

  getConfig(): Observable<FallaConfig> {
    return this.http.get<FallaConfig>(`${this.apiUrl}/config`);
  }

  create(falla: Partial<Falla>): Observable<any> {
    return this.http.post(this.apiUrl, falla);
  }

  update(id: number, falla: Partial<Falla>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, falla);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}
