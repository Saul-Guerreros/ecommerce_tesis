import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Departamento } from '../models/departamento.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class DepartamentosService {
  private apiUrl = `${environment.apiUrl}/admin/departamentos`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; estado?: string } = {}): Observable<Departamento[]> {
    let params = new HttpParams();

    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.estado !== undefined && filtros.estado !== '') {
      params = params.set('estado', filtros.estado);
    }

    return this.http
      .get<{ data: Departamento[] }>(this.apiUrl, { params })
      .pipe(map((res) => res.data));
  }

  create(departamento: Partial<Departamento>): Observable<any> {
    return this.http.post(this.apiUrl, departamento);
  }

  update(id: number, departamento: Partial<Departamento>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, departamento);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}