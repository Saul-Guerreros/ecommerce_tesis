import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Usuario, UsuarioFormValue, UsuariosPaginados, CatalogosUsuario, EstadoUsuario } from '../models/usuario.model';
import { environment } from '../../../../environments/environment';

/**
 * Sin headers manuales: el token ya lo agrega auth.interceptor.ts en
 * cada request (registrado globalmente en app.config.ts). Esto reemplaza
 * al service viejo de views/usuarios, que ponia el header a mano.
 */
@Injectable({ providedIn: 'root' })
export class UsuariosService {
  private apiUrl = `${environment.apiUrl}/admin/usuarios`;

  constructor(private http: HttpClient) {}

  list(page: number, search: string, estado: string, rolId: number | null): Observable<UsuariosPaginados> {
    let params = new HttpParams().set('page', String(page));
    if (search) params = params.set('search', search);
    if (estado) params = params.set('estado', estado);
    if (rolId) params = params.set('rol_id', String(rolId));

    return this.http.get<UsuariosPaginados>(this.apiUrl, { params });
  }

  catalogos(): Observable<CatalogosUsuario> {
    return this.http.get<CatalogosUsuario>(`${this.apiUrl}/catalogos`);
  }

  getById(id: number): Observable<{ data: Usuario }> {
    return this.http.get<{ data: Usuario }>(`${this.apiUrl}/${id}`);
  }

  create(payload: UsuarioFormValue): Observable<{ data: Usuario; message: string }> {
    return this.http.post<{ data: Usuario; message: string }>(this.apiUrl, payload);
  }

  update(id: number, payload: UsuarioFormValue): Observable<{ data: Usuario; message: string }> {
    return this.http.put<{ data: Usuario; message: string }>(`${this.apiUrl}/${id}`, payload);
  }

  cambiarEstado(id: number, estado: EstadoUsuario): Observable<{ data: Usuario; message: string }> {
    return this.http.patch<{ data: Usuario; message: string }>(`${this.apiUrl}/${id}/estado`, { estado });
  }
}