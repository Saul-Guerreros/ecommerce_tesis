import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { UnidadMedida } from '../models/unidad-medida.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class UnidadesMedidaService {
  private apiUrl = `${environment.apiUrl}/admin/unidades-medida`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; estado?: string } = {}): Observable<UnidadMedida[]> {
    let params = new HttpParams();
    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.estado !== undefined && filtros.estado !== '') params = params.set('estado', filtros.estado);

    return this.http.get<{ data: UnidadMedida[] }>(this.apiUrl, { params }).pipe(map((res) => res.data));
  }

  create(unidad: Partial<UnidadMedida>): Observable<any> {
    return this.http.post(this.apiUrl, unidad);
  }

  update(id: number, unidad: Partial<UnidadMedida>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, unidad);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}