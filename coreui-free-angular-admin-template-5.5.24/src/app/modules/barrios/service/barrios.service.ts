import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Barrio, CiudadOption } from '../models/barrio.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class BarriosService {
  private apiUrl = `${environment.apiUrl}/admin/barrios`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; ciudad?: number | null; estado?: string } = {}): Observable<Barrio[]> {
    let params = new HttpParams();

    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.ciudad) params = params.set('ciudad', String(filtros.ciudad));
    if (filtros.estado !== undefined && filtros.estado !== '') {
      params = params.set('estado', filtros.estado);
    }

    return this.http
      .get<{ data: Barrio[] }>(this.apiUrl, { params })
      .pipe(map((res) => res.data));
  }

  getConfig(): Observable<CiudadOption[]> {
    return this.http
      .get<{ ciudades: CiudadOption[] }>(`${this.apiUrl}/config`)
      .pipe(map((res) => res.ciudades));
  }

  create(barrio: Partial<Barrio>): Observable<any> {
    return this.http.post(this.apiUrl, barrio);
  }

  update(id: number, barrio: Partial<Barrio>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, barrio);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}
