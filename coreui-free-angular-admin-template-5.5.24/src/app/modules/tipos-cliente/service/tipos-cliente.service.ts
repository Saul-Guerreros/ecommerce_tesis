import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { TipoCliente } from '../models/tipo-cliente.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class TiposClienteService {
  private apiUrl = `${environment.apiUrl}/admin/tipos-cliente`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; estado?: string } = {}): Observable<TipoCliente[]> {
    let params = new HttpParams();
    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.estado !== undefined && filtros.estado !== '') params = params.set('estado', filtros.estado);

    return this.http.get<{ data: TipoCliente[] }>(this.apiUrl, { params }).pipe(map((res) => res.data));
  }

  create(tipo: Partial<TipoCliente>): Observable<any> {
    return this.http.post(this.apiUrl, tipo);
  }

  update(id: number, tipo: Partial<TipoCliente>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, tipo);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}