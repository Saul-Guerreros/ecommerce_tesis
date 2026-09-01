import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Ciudad, DepartamentoOption } from '../models/ciudad.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class CiudadesService {
  private apiUrl = `${environment.apiUrl}/admin/ciudades`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; departamento?: number | null; estado?: string } = {}): Observable<Ciudad[]> {
    let params = new HttpParams();

    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.departamento) params = params.set('departamento', String(filtros.departamento));
    if (filtros.estado !== undefined && filtros.estado !== '') {
      params = params.set('estado', filtros.estado);
    }

    return this.http
      .get<{ data: Ciudad[] }>(this.apiUrl, { params })
      .pipe(map((res) => res.data));
  }

  getDepartamentos(): Observable<DepartamentoOption[]> {
    return this.http
      .get<{ departamentos: DepartamentoOption[] }>(`${this.apiUrl}/config`)
      .pipe(map((res) => res.departamentos));
  }

  create(ciudad: Partial<Ciudad>): Observable<any> {
    return this.http.post(this.apiUrl, ciudad);
  }

  update(id: number, ciudad: Partial<Ciudad>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, ciudad);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}