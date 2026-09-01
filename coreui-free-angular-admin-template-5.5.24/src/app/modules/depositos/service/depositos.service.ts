import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Deposito, SucursalOption } from '../models/deposito.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class DepositosService {
  private apiUrl = `${environment.apiUrl}/admin/depositos`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; sucursal?: number | null; estado?: string } = {}): Observable<Deposito[]> {
    let params = new HttpParams();

    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.sucursal) params = params.set('sucursal', String(filtros.sucursal));
    if (filtros.estado !== undefined && filtros.estado !== '') {
      params = params.set('estado', filtros.estado);
    }

    return this.http
      .get<{ data: Deposito[] }>(this.apiUrl, { params })
      .pipe(map((res) => res.data));
  }

  getSucursales(): Observable<SucursalOption[]> {
    return this.http
      .get<{ sucursales: SucursalOption[] }>(`${this.apiUrl}/config`)
      .pipe(map((res) => res.sucursales));
  }

  create(deposito: Partial<Deposito>): Observable<any> {
    return this.http.post(this.apiUrl, deposito);
  }

  update(id: number, deposito: Partial<Deposito>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, deposito);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }

  marcarPrincipal(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/principal`, {});
  }
}