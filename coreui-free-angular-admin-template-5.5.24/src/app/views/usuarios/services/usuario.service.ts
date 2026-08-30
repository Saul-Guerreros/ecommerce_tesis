import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable, finalize } from 'rxjs';
import { URL_SERVICIOS } from '../../../config/config';
import { AuthService } from '../../../auth/services/auth.service';
import {
  CatalogosUsuario,
  EstadoUsuario,
  Usuario,
  UsuarioFormValue,
} from '../usuario.model';

export interface UsuariosPaginados {
  data: Usuario[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

@Injectable({
  providedIn: 'root',
})
export class UsuarioService {
  isLoadingSubject = new BehaviorSubject<boolean>(false);
  isLoading$: Observable<boolean> = this.isLoadingSubject.asObservable();

  private apiUrl = `${URL_SERVICIOS}/admin/usuarios`;

  constructor(private http: HttpClient, private authService: AuthService) {}

  private get headers(): HttpHeaders {
    const token = this.authService.getToken();
    return new HttpHeaders({ Authorization: `Bearer ${token}` });
  }

  list(page: number = 1, search: string = '', estado: string = '', rolId: number | null = null): Observable<UsuariosPaginados> {
    this.isLoadingSubject.next(true);
    let url = `${this.apiUrl}?page=${page}&search=${encodeURIComponent(search)}`;
    if (estado) url += `&estado=${estado}`;
    if (rolId) url += `&rol_id=${rolId}`;

    return this.http
      .get<UsuariosPaginados>(url, { headers: this.headers })
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }

  catalogos(): Observable<CatalogosUsuario> {
    return this.http.get<CatalogosUsuario>(`${this.apiUrl}/catalogos`, { headers: this.headers });
  }

  create(data: UsuarioFormValue): Observable<{ message: string; data: Usuario }> {
    this.isLoadingSubject.next(true);
    return this.http
      .post<{ message: string; data: Usuario }>(this.apiUrl, data, { headers: this.headers })
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }

  update(id: number, data: UsuarioFormValue): Observable<{ message: string; data: Usuario }> {
    this.isLoadingSubject.next(true);
    return this.http
      .put<{ message: string; data: Usuario }>(`${this.apiUrl}/${id}`, data, { headers: this.headers })
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }

  cambiarEstado(id: number, estado: EstadoUsuario): Observable<{ message: string; data: Usuario }> {
    this.isLoadingSubject.next(true);
    return this.http
      .patch<{ message: string; data: Usuario }>(`${this.apiUrl}/${id}/estado`, { estado }, { headers: this.headers })
      .pipe(finalize(() => this.isLoadingSubject.next(false)));
  }
}
