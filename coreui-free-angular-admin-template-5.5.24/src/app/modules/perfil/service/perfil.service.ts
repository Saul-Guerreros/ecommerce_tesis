import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { URL_SERVICIOS } from '../../../config/config';
import { CambioPassword, Perfil } from '../models/perfil.model';

@Injectable({ providedIn: 'root' })
export class PerfilService {
  private apiUrl = `${URL_SERVICIOS}/admin/perfil`;

  constructor(private http: HttpClient) {}

  get(): Observable<Perfil> {
    return this.http.get<{ data: Perfil }>(this.apiUrl).pipe(map((res) => res.data));
  }

  update(data: FormData): Observable<{ message: string; data: Perfil }> {
    return this.http.post<{ message: string; data: Perfil }>(this.apiUrl, data);
  }

  cambiarPassword(data: CambioPassword): Observable<{ message: string }> {
    return this.http.patch<{ message: string }>(`${this.apiUrl}/password`, data);
  }
}