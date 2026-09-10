import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { Rol, RolConfig } from '../models/rol.model';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class RolesService {
  private apiUrl = `${environment.apiUrl}/admin/roles`;

  constructor(private http: HttpClient) {}

  getAll(filtros: { search?: string; estado?: string } = {}): Observable<Rol[]> {
    let params = new HttpParams();
    if (filtros.search) params = params.set('search', filtros.search);
    if (filtros.estado !== undefined && filtros.estado !== '') params = params.set('estado', filtros.estado);

    return this.http.get<{ data: Rol[] }>(this.apiUrl, { params }).pipe(map((res) => res.data));
  }

  getConfig(): Observable<RolConfig> {
    return this.http.get<RolConfig>(`${this.apiUrl}/config`);
  }

  getById(id: number): Observable<Rol> {
    return this.http.get<{ data: Rol }>(`${this.apiUrl}/${id}`).pipe(map((res) => res.data));
  }

  create(rol: Partial<Rol>): Observable<any> {
    return this.http.post(this.apiUrl, rol);
  }

  update(id: number, rol: Partial<Rol>): Observable<any> {
    return this.http.put(`${this.apiUrl}/${id}`, rol);
  }

  delete(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  toggleEstado(id: number): Observable<any> {
    return this.http.patch(`${this.apiUrl}/${id}/estado`, {});
  }
}