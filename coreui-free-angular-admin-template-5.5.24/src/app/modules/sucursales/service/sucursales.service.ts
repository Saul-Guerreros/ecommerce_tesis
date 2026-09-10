import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Sucursal, SucursalConfig } from '../models/sucursal.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class SucursalesService {
  private apiUrl = `${environment.apiUrl}/admin/sucursales`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; empresa?: number | null; estado?: string } = {}): Observable<Sucursal[]> {
    let params = new HttpParams();

    if (filtros.search)  params = params.set('search', filtros.search);
    if (filtros.empresa) params = params.set('empresa', String(filtros.empresa));
    if (filtros.estado !== undefined && filtros.estado !== '') {
      params = params.set('estado', filtros.estado);
    }

    return this.http
      .get<{ data: Sucursal[] }>(this.apiUrl, { params })
      .pipe(map((res) => res.data));
  }

  getConfig(): Observable<SucursalConfig> {
    return this.http.get<SucursalConfig>(`${this.apiUrl}/config`);
  }

  getById(id: number): Observable<Sucursal> {
    return this.http
      .get<{ data: Sucursal }>(`${this.apiUrl}/${id}`)
      .pipe(map((res) => res.data));
  }

  create(sucursal: Partial<Sucursal>): Observable<any> {
    return this.http.post(this.apiUrl, sucursal);
  }

  update(id: number, sucursal: Partial<Sucursal>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, sucursal);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}