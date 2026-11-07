import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { PaginaNosotros } from '../models/pagina-nosotros.model';
import { environment } from '../../../../environments/environment';

interface RespuestaPagina {
  message?: string;
  data: PaginaNosotros;
}

@Injectable({ providedIn: 'root' })
export class PaginaNosotrosService {
  private apiUrl = `${environment.apiUrl}/admin/pagina-nosotros`;

  constructor(private http: HttpClient) {}

  get(): Observable<PaginaNosotros> {
    return this.http.get<RespuestaPagina>(this.apiUrl).pipe(map((res) => res.data));
  }

  update(payload: Partial<PaginaNosotros>): Observable<RespuestaPagina> {
    return this.http.put<RespuestaPagina>(this.apiUrl, payload);
  }

  subirImagen(archivo: File): Observable<RespuestaPagina> {
    const form = new FormData();
    form.append('imagen', archivo);
    return this.http.post<RespuestaPagina>(`${this.apiUrl}/imagen`, form);
  }

  eliminarImagen(): Observable<RespuestaPagina> {
    return this.http.delete<RespuestaPagina>(`${this.apiUrl}/imagen`);
  }
}