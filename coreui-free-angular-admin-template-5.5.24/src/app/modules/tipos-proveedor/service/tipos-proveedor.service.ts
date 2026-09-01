import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { TipoProveedor } from '../models/tipo-proveedor.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class TiposProveedorService {
  private apiUrl = `${environment.apiUrl}/admin/tipos-proveedor`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; estado?: string } = {}): Observable<TipoProveedor[]> {
    let params = new HttpParams();

    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.estado !== undefined && filtros.estado !== '') {
      params = params.set('estado', filtros.estado);
    }

    return this.http
      .get<{ data: TipoProveedor[] }>(this.apiUrl, { params })
      .pipe(map((res) => res.data));
  }

  // Combo para el formulario de proveedor.
  getSelect(): Observable<TipoProveedor[]> {
    return this.http
      .get<{ data: TipoProveedor[] }>(`${this.apiUrl}/select`)
      .pipe(map((res) => res.data));
  }

  create(tipo: Partial<TipoProveedor>): Observable<any> {
    return this.http.post(this.apiUrl, tipo);
  }

  update(id: number, tipo: Partial<TipoProveedor>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, tipo);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}