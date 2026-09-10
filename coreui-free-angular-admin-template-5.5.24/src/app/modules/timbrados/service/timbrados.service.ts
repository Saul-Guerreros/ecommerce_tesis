import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Timbrado, TimbradoConfig } from '../models/timbrado.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class TimbradosService {
  private apiUrl = `${environment.apiUrl}/admin/timbrados`;

  constructor(private http: HttpClient) {}

  getAll(
    filtros: { search?: string; sucursal?: number | null; tipo?: number | null; estado?: string } = {}
  ): Observable<Timbrado[]> {
    let params = new HttpParams();

    if (filtros.search)   params = params.set('search', filtros.search);
    if (filtros.sucursal) params = params.set('sucursal', String(filtros.sucursal));
    if (filtros.tipo)     params = params.set('tipo', String(filtros.tipo));
    if (filtros.estado !== undefined && filtros.estado !== '') {
      params = params.set('estado', filtros.estado);
    }

    return this.http
      .get<{ data: Timbrado[] }>(this.apiUrl, { params })
      .pipe(map((res) => res.data));
  }

  getConfig(): Observable<TimbradoConfig> {
    return this.http.get<TimbradoConfig>(`${this.apiUrl}/config`);
  }

  create(timbrado: Partial<Timbrado>): Observable<any> {
    return this.http.post(this.apiUrl, timbrado);
  }

  update(id: number, timbrado: Partial<Timbrado>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, timbrado);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}